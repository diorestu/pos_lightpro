import type { LinkComponentBaseProps } from '@inertiajs/core';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(
    href: NonNullable<LinkComponentBaseProps['href']>,
): string {
    return typeof href === 'string' ? href : href.url;
}

/**
 * Format kuantitas atau angka stok:
 * Jika tidak memiliki pecahan atau desimal bernilai 0 (misal 10 atau 10.00), jangan tampilkan angka di belakang koma (hasil: "10").
 * Jika memiliki desimal bernilai bukan 0 (misal 10.50 atau 10.25), tampilkan desimal tanpa trailing zero (hasil: "10,5", "10,25").
 */
export function formatStock(value: number | string | null | undefined): string {
    if (value === null || value === undefined || value === '') return '0';
    const num = typeof value === 'number' ? value : parseFloat(value.toString());
    if (isNaN(num)) return '0';
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(num);
}

/**
 * Format angka untuk nilai atribut input numerik (HTML5 input type="number"):
 * Menghasilkan string numerik standar tanpa desimal jika bernilai bulat (misal "10" bukan "10.00").
 */
export function formatStockInput(value: number | string | null | undefined): string {
    if (value === null || value === undefined || value === '') return '0';
    const num = typeof value === 'number' ? value : parseFloat(value.toString());
    if (isNaN(num)) return '0';
    return (Math.round(num * 100) / 100).toString();
}

/**
 * Periksa apakah stok bahan baku atau produk dalam kondisi menipis (<= minStock)
 */
export function isLowStock(
    stock: number | string | null | undefined,
    minStock: number | string | null | undefined,
): boolean {
    const s = typeof stock === 'number' ? stock : parseFloat(stock?.toString() || '0');
    const m = typeof minStock === 'number' ? minStock : parseFloat(minStock?.toString() || '0');
    return (isNaN(s) ? 0 : s) <= (isNaN(m) ? 0 : m);
}
