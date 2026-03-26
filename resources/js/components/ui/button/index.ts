import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-semibold transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        default:
          "bg-primary text-primary-foreground shadow-sm hover:bg-[var(--primary-dark)]",
        destructive:
          "bg-destructive text-white shadow-sm hover:bg-red-600 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40",
        outline:
          "border border-border bg-background text-primary shadow-xs hover:bg-primary/10 dark:bg-input/30 dark:border-input dark:hover:bg-input/50",
        secondary:
          "bg-secondary text-secondary-foreground shadow-xs hover:bg-slate-200 dark:hover:bg-secondary/80",
        ghost:
          "text-muted-foreground hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50",
        link: "text-primary underline-offset-4 hover:underline",
        success:
          "bg-success text-white shadow-sm hover:bg-emerald-600",
        warning:
          "bg-warning text-white shadow-sm hover:bg-amber-600",
      },
      size: {
        "default": "h-11 px-6 py-2.5 has-[>svg]:px-4",
        "sm": "h-9 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5",
        "lg": "h-12 rounded-md px-8 has-[>svg]:px-6",
        "icon": "size-11",
        "icon-sm": "size-9",
        "icon-lg": "size-12",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)
export type ButtonVariants = VariantProps<typeof buttonVariants>
