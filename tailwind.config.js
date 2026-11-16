import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,jsx}',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },

            colors: {
                /**
                 * Hydrological brand scale: deep gauging-station teal.
                 */
                brand: {
                    50: '#eff9fb',
                    100: '#d7f0f6',
                    200: '#b0e2ec',
                    300: '#7ccbdb',
                    400: '#45abc2',
                    500: '#2a8da6',
                    600: '#237691',
                    700: '#1f6076',
                    800: '#1f4f60',
                    900: '#1d4251',
                    950: '#0c2a35',
                },

                /**
                 * Semantic surfaces driven by the CSS variables in app.css so
                 * light and dark themes stay consistent across every module.
                 */
                canvas: 'rgb(var(--canvas) / <alpha-value>)',
                surface: 'rgb(var(--surface) / <alpha-value>)',
                line: 'rgb(var(--line) / <alpha-value>)',
                ink: 'rgb(var(--ink) / <alpha-value>)',
                muted: 'rgb(var(--muted) / <alpha-value>)',
            },

            spacing: {
                sidebar: '17rem',
                'sidebar-collapsed': '4.75rem',
            },

            boxShadow: {
                card: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.06)',
                lift: '0 18px 40px -24px rgb(15 23 42 / 0.45)',
            },

            transitionTimingFunction: {
                'out-soft': 'cubic-bezier(0.22, 1, 0.36, 1)',
            },

            keyframes: {
                'fade-in': {
                    from: { opacity: '0' },
                    to: { opacity: '1' },
                },
                'pop-in': {
                    from: { opacity: '0', transform: 'translateY(-6px) scale(0.98)' },
                    to: { opacity: '1', transform: 'translateY(0) scale(1)' },
                },
                'slide-in-left': {
                    from: { transform: 'translateX(-100%)' },
                    to: { transform: 'translateX(0)' },
                },
            },

            animation: {
                'fade-in': 'fade-in 150ms ease-out',
                'pop-in': 'pop-in 140ms cubic-bezier(0.22, 1, 0.36, 1)',
                'slide-in-left': 'slide-in-left 220ms cubic-bezier(0.22, 1, 0.36, 1)',
            },
        },
    },

    plugins: [forms],
};
