import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'system-ui', '-apple-system', 'BlinkMacSystemFont', ...defaultTheme.fontFamily.sans],
                display: ['Space Grotesk', '-apple-system', 'sans-serif'],
            },
            colors: {
                brand: {
                    50:  '#ecfeff',
                    100: '#cffafe',
                    200: '#a5f3fc',
                    300: '#67e8f9',
                    400: '#22d3ee',
                    500: '#06b6d4',
                    600: '#0891b2',
                    700: '#0e7490',
                    800: '#155e75',
                    900: '#164e63',
                    950: '#083344',
                },
                bg: 'rgb(var(--color-bg-rgb, 10 14 23) / <alpha-value>)',
                canvas: 'rgb(var(--color-canvas-rgb, 246 248 251) / <alpha-value>)',
                card: 'rgb(var(--color-card-rgb, 19 24 38) / <alpha-value>)',
                field: 'rgb(var(--color-field-rgb, 27 34 51) / <alpha-value>)',
                accent: 'rgb(var(--color-accent-rgb, 20 199 224) / <alpha-value>)',
                'accent-dark': 'rgb(var(--color-accent-dark-rgb, 15 166 188) / <alpha-value>)',
                danger: 'rgb(var(--color-danger-rgb, 255 92 108) / <alpha-value>)',
                ok: 'rgb(var(--color-ok-rgb, 31 169 113) / <alpha-value>)',
                pending: 'rgb(var(--color-pending-rgb, 226 166 59) / <alpha-value>)',
                border: 'rgb(var(--color-border-rgb, 35 43 61) / <alpha-value>)',
                rail: 'rgb(var(--color-rail-rgb, 16 24 38) / <alpha-value>)',
                'rail-hover': 'rgb(var(--color-rail-hover-rgb, 27 37 55) / <alpha-value>)',
                'rail-text': 'rgb(var(--color-rail-text-rgb, 154 165 184) / <alpha-value>)',
                text: {
                    DEFAULT: 'rgb(var(--color-text-rgb, 245 247 250) / <alpha-value>)',
                    dim: 'rgb(var(--color-text-dim-rgb, 139 147 167) / <alpha-value>)',
                },
                surface: {
                    DEFAULT: 'rgb(var(--color-surface-rgb, 255 255 255) / <alpha-value>)',
                    muted: '#f8fafc',
                    subtle: '#f1f5f9',
                    border: '#e2e8f0',
                },
                ink: {
                    DEFAULT: '#0f172a',
                    muted: '#475569',
                    subtle: '#64748b',
                    disabled: '#94a3b8',
                },
                status: {
                    success: { DEFAULT: '#059669', soft: '#d1fae5', ink: '#065f46' },
                    warning: { DEFAULT: '#d97706', soft: '#fef3c7', ink: '#92400e' },
                    danger:  { DEFAULT: '#dc2626', soft: '#fee2e2', ink: '#991b1b' },
                    info:    { DEFAULT: '#0e7490', soft: '#cffafe', ink: '#164e63' },
                    neutral: { DEFAULT: '#475569', soft: '#f1f5f9', ink: '#334155' },
                },
                frame: {
                    sidebar: '#083344',
                    header:  '#0f172a',
                    backdrop: 'rgba(15, 23, 42, 0.55)',
                },
            },
            boxShadow: {
                xs: '0 1px 2px 0 rgb(15 23 42 / 0.05)',
                sm: '0 1px 3px 0 rgb(15 23 42 / 0.08), 0 1px 2px -1px rgb(15 23 42 / 0.06)',
                DEFAULT: '0 4px 6px -1px rgb(15 23 42 / 0.08), 0 2px 4px -2px rgb(15 23 42 / 0.06)',
                md: '0 10px 15px -3px rgb(15 23 42 / 0.08), 0 4px 6px -4px rgb(15 23 42 / 0.06)',
                lg: '0 20px 25px -5px rgb(15 23 42 / 0.08), 0 8px 10px -6px rgb(15 23 42 / 0.06)',
            },
            borderRadius: {
                sm2: '5px',
            },
        },
    },

    plugins: [forms],
};
