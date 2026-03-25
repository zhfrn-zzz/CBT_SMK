import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Badge } from "./Badge.vue"

export const badgeVariants = cva(
  "inline-flex items-center justify-center rounded-full border px-2.5 py-1 text-xs font-semibold w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden",
  {
    variants: {
      variant: {
        default:
          "border-transparent bg-primary/10 text-primary [a&]:hover:bg-primary/20",
        secondary:
          "border-transparent bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300 [a&]:hover:bg-slate-200",
        destructive:
          "border-transparent bg-destructive text-white [a&]:hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60",
        outline:
          "text-foreground [a&]:hover:bg-accent [a&]:hover:text-accent-foreground",
        success:
          "border-transparent bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400",
        warning:
          "border-transparent bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-400",
        danger:
          "border-transparent bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-400",
        info:
          "border-transparent bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-400",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)
export type BadgeVariants = VariantProps<typeof badgeVariants>
