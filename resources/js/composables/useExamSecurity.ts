import { onMounted, onUnmounted } from 'vue';
import axios from 'axios';

interface UseExamSecurityOptions {
    attemptId: number;
    enabled: boolean;
}

export function useExamSecurity(options: UseExamSecurityOptions) {
    if (!options.enabled) {
        return { cleanup: () => {} };
    }

    function logSecurityEvent(eventType: string, description: string): void {
        axios.post('/api/exam/log-activity', {
            attempt_id: options.attemptId,
            event_type: eventType,
            description,
        }).catch(() => {
            // fire and forget
        });
    }

    // 1. Keyboard prevention
    function handleKeyDown(e: KeyboardEvent): void {
        // Ctrl+C, Ctrl+V, Ctrl+A, Ctrl+S, Ctrl+U, Ctrl+P
        if (e.ctrlKey && ['c', 'v', 'p', 's', 'a', 'u'].includes(e.key.toLowerCase())) {
            e.preventDefault();
            e.stopPropagation();
            const keyCombo = `Ctrl+${e.key.toUpperCase()}`;
            logSecurityEvent('keyboard_shortcut', `Blocked: ${keyCombo}`);
            return;
        }

        // Ctrl+Shift+I (DevTools)
        if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'i') {
            e.preventDefault();
            e.stopPropagation();
            logSecurityEvent('devtools_attempt', 'Blocked: Ctrl+Shift+I');
            return;
        }

        // Ctrl+Shift+J (DevTools Console)
        if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'j') {
            e.preventDefault();
            e.stopPropagation();
            logSecurityEvent('devtools_attempt', 'Blocked: Ctrl+Shift+J');
            return;
        }

        // Ctrl+Shift+C (DevTools Inspect)
        if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'c') {
            e.preventDefault();
            e.stopPropagation();
            logSecurityEvent('devtools_attempt', 'Blocked: Ctrl+Shift+C');
            return;
        }

        // F12 (DevTools)
        if (e.key === 'F12') {
            e.preventDefault();
            e.stopPropagation();
            logSecurityEvent('devtools_attempt', 'Blocked: F12');
            return;
        }

        // PrintScreen
        if (e.key === 'PrintScreen') {
            e.preventDefault();
            e.stopPropagation();
            logSecurityEvent('screenshot_attempt', 'PrintScreen key pressed');
            return;
        }
    }

    // 2. Print prevention
    function handleBeforePrint(): void {
        logSecurityEvent('print_attempt', 'Print dialog triggered');
    }

    // 3. Paste prevention
    function handlePaste(e: ClipboardEvent): void {
        e.preventDefault();
        logSecurityEvent('copy_paste_attempt', 'Paste attempt blocked');
    }

    // 4. DevTools detection (window size heuristic)
    let devToolsInterval: ReturnType<typeof setInterval> | null = null;
    let devToolsWasOpen = false;

    function detectDevTools(): void {
        const threshold = 160;
        const widthDiff = window.outerWidth - window.innerWidth > threshold;
        const heightDiff = window.outerHeight - window.innerHeight > threshold;

        const isOpen = widthDiff || heightDiff;

        if (isOpen && !devToolsWasOpen) {
            devToolsWasOpen = true;
            logSecurityEvent('devtools_open', 'DevTools detected open via size check');
        } else if (!isOpen && devToolsWasOpen) {
            devToolsWasOpen = false;
        }
    }

    // 5. CSS injection for anti-select and print prevention
    function injectSecurityCSS(): void {
        const style = document.createElement('style');
        style.id = 'exam-security-css';
        style.textContent = `
            .exam-security-active {
                user-select: none !important;
                -webkit-user-select: none !important;
                -webkit-touch-callout: none !important;
            }
            @media print {
                body * {
                    display: none !important;
                }
                body::after {
                    content: "Pencetakan tidak diizinkan saat ujian berlangsung.";
                    display: block !important;
                    font-size: 2rem;
                    text-align: center;
                    padding: 2rem;
                    color: #000;
                }
            }
        `;
        document.head.appendChild(style);
        document.body.classList.add('exam-security-active');
    }

    function removeSecurityCSS(): void {
        const style = document.getElementById('exam-security-css');
        if (style) style.remove();
        document.body.classList.remove('exam-security-active');
    }

    function setup(): void {
        injectSecurityCSS();
        document.addEventListener('keydown', handleKeyDown, true);
        document.addEventListener('paste', handlePaste, true);
        window.addEventListener('beforeprint', handleBeforePrint);
        devToolsInterval = setInterval(detectDevTools, 3000);
    }

    function cleanup(): void {
        removeSecurityCSS();
        document.removeEventListener('keydown', handleKeyDown, true);
        document.removeEventListener('paste', handlePaste, true);
        window.removeEventListener('beforeprint', handleBeforePrint);
        if (devToolsInterval) {
            clearInterval(devToolsInterval);
            devToolsInterval = null;
        }
    }

    onMounted(setup);
    onUnmounted(cleanup);

    return { cleanup };
}
