import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import daisyui from 'daisyui';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.scss',
        './resources/**/*.vue',
        './config/**/*.php',
        './vendor/robsontenorio/mary/src/View/Components/**/*.php',
    ],
    blocklist: ['[file:line]'],

    theme: {
        extend: {
            colors: {
                cream: 'var(--color-cream)',
                'warm-white': 'var(--color-warm-white)',
                paw: 'var(--color-paw)',
                'paw-dark': 'var(--color-paw-dark)',
                'paw-light': 'var(--color-paw-light)',
                bark: 'var(--color-bark)',
                fur: 'var(--color-fur)',
                whisker: 'var(--color-whisker)',
                leaf: 'var(--color-leaf)',
                'leaf-light': 'var(--color-leaf-light)',
                sky: 'var(--color-sky)',
                'sky-light': 'var(--color-sky-light)',
                rose: 'var(--color-rose)',
                'rose-light': 'var(--color-rose-light)',
                amber: 'var(--color-amber)',
                'amber-light': 'var(--color-amber-light)',
            },
            fontFamily: {
                display: ['"GT Sectra"', 'Georgia', '"Times New Roman"', ...defaultTheme.fontFamily.serif],
                body: ['"Söhne"', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            borderRadius: {
                none: '0px',
                DEFAULT: 'var(--radius-soft)',
                sm: 'var(--radius-soft)',
                md: 'var(--radius-soft)',
                lg: 'var(--radius-card)',
                xl: 'var(--radius-card)',
                '2xl': 'var(--radius-card)',
                '3xl': 'var(--radius-panel)',
                full: '9999px',
                pill: 'var(--radius-pill)',
            },
            boxShadow: {
                card: 'none',
                'card-hover': '0 2px 16px rgba(28,26,23,0.06)',
                button: 'none',
                input: 'none',
            },
            fontSize: {
                '2xs': ['0.625rem', { lineHeight: '0.875rem' }],
            },
        },
    },

    plugins: [forms, typography, daisyui],

    daisyui: {
        themes: [
            {
                // Custom theme that maps daisyUI tokens to the project's warm palette.
                petssocnet: {
                    'primary': '#C0512F',
                    'primary-content': '#FBF6EE',
                    'secondary': '#2F5B4F',
                    'secondary-content': '#FBF6EE',
                    'accent': '#9B5B32',
                    'accent-content': '#FBF6EE',
                    'neutral': '#1C1A17',
                    'neutral-content': '#FAF7F2',
                    'base-100': '#FAF7F2',
                    'base-200': '#F1E3CF',
                    'base-300': '#DED2C3',
                    'base-content': '#1C1A17',
                    'info': '#2F5B4F',
                    'success': '#4F8A4F',
                    'warning': '#C9822F',
                    'error': '#B33A3A',
                },
            },
        ],
        darkTheme: false,
        logs: false,
    },
};
