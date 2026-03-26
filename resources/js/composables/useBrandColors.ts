import { usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const DEFAULT_PRIMARY = '#2563eb';
const DEFAULT_SECONDARY = '#64748b';

function hexToHsl(hex: string): string {
    hex = hex.replace(/^#/, '');
    const r = parseInt(hex.substring(0, 2), 16) / 255;
    const g = parseInt(hex.substring(2, 4), 16) / 255;
    const b = parseInt(hex.substring(4, 6), 16) / 255;

    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    let h = 0;
    let s = 0;
    const l = (max + min) / 2;

    if (max !== min) {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case r:
                h = ((g - b) / d + (g < b ? 6 : 0)) * 60;
                break;
            case g:
                h = ((b - r) / d + 2) * 60;
                break;
            case b:
                h = ((r - g) / d + 4) * 60;
                break;
        }
    }

    return `${h.toFixed(1)} ${(s * 100).toFixed(1)}% ${(l * 100).toFixed(1)}%`;
}

function darkenHex(hex: string, amount: number): string {
    hex = hex.replace(/^#/, '');
    let r = parseInt(hex.substring(0, 2), 16);
    let g = parseInt(hex.substring(2, 4), 16);
    let b = parseInt(hex.substring(4, 6), 16);
    r = Math.max(0, Math.round(r * (1 - amount)));
    g = Math.max(0, Math.round(g * (1 - amount)));
    b = Math.max(0, Math.round(b * (1 - amount)));
    return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
}

function isContrastLight(hex: string): boolean {
    hex = hex.replace(/^#/, '');
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    return luminance > 0.5;
}

function applyColors(primary: string, secondary: string): void {
    if (typeof document === 'undefined') return;

    const root = document.documentElement;
    const primaryHsl = hexToHsl(primary);
    const primaryDark = hexToHsl(darkenHex(primary, 0.15));
    const primaryFg = isContrastLight(primary) ? 'hsl(0 0% 0%)' : 'hsl(0 0% 100%)';

    // Primary color variables
    root.style.setProperty('--primary', `hsl(${primaryHsl})`);
    root.style.setProperty('--primary-dark', `hsl(${primaryDark})`);
    root.style.setProperty('--primary-foreground', primaryFg);
    root.style.setProperty('--ring', `hsl(${primaryHsl})`);
    root.style.setProperty('--sidebar-primary', `hsl(${primaryHsl})`);
    root.style.setProperty('--sidebar-primary-foreground', primaryFg);
    root.style.setProperty('--sidebar-ring', `hsl(${primaryHsl})`);
    root.style.setProperty('--chart-1', `hsl(${primaryHsl})`);

    // Secondary color variables
    const secondaryHsl = hexToHsl(secondary);
    const secondaryFg = isContrastLight(secondary) ? 'hsl(0 0% 0%)' : 'hsl(0 0% 100%)';
    root.style.setProperty('--secondary', `hsl(${secondaryHsl})`);
    root.style.setProperty('--secondary-foreground', secondaryFg);
}

/**
 * Apply brand colors from settings immediately on page load (before Vue mounts).
 * Uses server-injected window.__BRAND_COLORS__ for instant application.
 */
export function initializeBrandColors(): void {
    if (typeof window === 'undefined') return;

    const colors = (window as any).__BRAND_COLORS__;
    if (colors) {
        applyColors(
            colors.primary || DEFAULT_PRIMARY,
            colors.secondary || DEFAULT_SECONDARY,
        );
    }
}

/**
 * Composable that watches app_settings and re-applies brand colors reactively.
 * Use in App-level components to keep colors in sync after settings are saved.
 */
export function useBrandColors(): void {
    const page = usePage();
    const appSettings = computed(() => (page.props as any).app_settings ?? {});

    const primary = computed(() => appSettings.value.primary_color || DEFAULT_PRIMARY);
    const secondary = computed(() => appSettings.value.secondary_color || DEFAULT_SECONDARY);

    watch(
        [primary, secondary],
        ([p, s]) => applyColors(p, s),
        { immediate: true },
    );
}
