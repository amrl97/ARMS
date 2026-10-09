import type { ClassValue } from "clsx"
import { clsx } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function toUrl(href: string | URL | { url: string }): string {
  if (typeof href === "string") {
    return href
  }

  return href instanceof URL ? href.toString() : href.url
}
