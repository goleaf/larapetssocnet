import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
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
                sm: '6px',
                md: '12px',
                lg: '20px',
                xl: '28px',
                pill: '9999px',
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

    plugins: [forms, typography],
};
