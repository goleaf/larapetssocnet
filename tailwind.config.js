import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import daisyui from 'daisyui';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
        './vendor/robsontenorio/mary/src/View/Components/**/*.php',
    ],

    theme: {
        extend: {
            colors: {
                cream: '#FDF6EC',
                'warm-white': '#FFFBF5',
                paw: '#E8834A',
                'paw-dark': '#C9602A',
                'paw-light': '#FDE8D8',
                bark: '#3D2B1F',
                fur: '#7A5C4A',
                whisker: '#C4A882',
                leaf: '#5A9A6F',
                'leaf-light': '#E6F4EB',
                sky: '#4A85C9',
                'sky-light': '#E6F0FA',
                rose: '#C94A5A',
                'rose-light': '#FAE6E8',
                amber: '#D4850A',
                'amber-light': '#FEF3DC',
            },
            fontFamily: {
                display: ['"Playfair Display"', 'Georgia', 'serif'],
                body: ['"DM Sans"', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            borderRadius: {
                none: '0px',
                DEFAULT: '0px',
                sm: '0px',
                md: '0px',
                lg: '0px',
                xl: '0px',
                '2xl': '0px',
                '3xl': '0px',
                full: '0px',
                pill: '0px',
            },
            boxShadow: {
                card: '0 2px 12px rgba(61,43,31,0.08)',
                'card-hover': '0 6px 24px rgba(61,43,31,0.14)',
                button: '0 2px 8px rgba(232,131,74,0.25)',
                input: '0 0 0 3px rgba(232,131,74,0.15)',
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
                    'primary': '#E8834A', // paw
                    'primary-content': '#ffffff',
                    'secondary': '#D4850A', // amber
                    'secondary-content': '#ffffff',
                    'accent': '#4A85C9', // sky
                    'accent-content': '#ffffff',
                    'neutral': '#3D2B1F', // bark
                    'neutral-content': '#FDF6EC',
                    'base-100': '#FDF6EC', // cream
                    'base-200': '#F9F0E6',
                    'base-300': '#F0E4D0',
                    'base-content': '#3D2B1F', // bark
                    'info': '#4A85C9',
                    'success': '#5A9A6F', // leaf
                    'warning': '#D4850A',
                    'error': '#C94A5A', // rose
                },
            },
        ],
        darkTheme: false,
        logs: false,
    },
};
