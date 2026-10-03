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
                surface: {
                    DEFAULT: '#ffffff',
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
