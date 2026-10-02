/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: [
    './index.html',
    './src/**/*.{js,jsx,ts,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        surface: {
          dark: '#0F172A',
          card: '#1E293B',
          panel: '#162032',
          border: '#334155',
          muted: '#64748B',
        },
        primary: {
          DEFAULT: '#06B6D4',
          pressed: '#0891B2',
          soft: 'rgba(6, 182, 212, 0.16)',
        },
        success: {
          DEFAULT: '#0E7490',
          soft: 'rgba(6, 182, 212, 0.16)',
        },
        danger: {
          DEFAULT: '#164E63',
          soft: 'rgba(15, 23, 42, 0.6)',
        },
        warning: {
          DEFAULT: '#475569',
          surface: '#1E293B',
          text: '#CBD5E1',
          soft: 'rgba(71, 85, 105, 0.18)',
        },
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'Segoe UI', 'Roboto', '-apple-system', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'monospace'],
      },
      fontSize: {
        payout: ['30px', { lineHeight: '36px', letterSpacing: '-0.02em', fontWeight: '700' }],
        'screen-title': ['20px', { lineHeight: '28px', letterSpacing: '-0.01em', fontWeight: '600' }],
        body: ['15px', { lineHeight: '21px', fontWeight: '500' }],
        helper: ['13px', { lineHeight: '18px', fontWeight: '400' }],
      },
      boxShadow: {
        pop: '0 22px 60px -20px rgba(15, 23, 42, 0.55)',
        card: '0 10px 30px -14px rgba(2, 6, 23, 0.55)',
      },
      borderRadius: {
        xl2: '18px',
      },
    },
  },
  plugins: [],
};
