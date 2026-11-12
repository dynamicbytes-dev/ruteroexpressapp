import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Tokens de marca Motoruta.
 *
 * Paleta: morado oscuro #310F38, cian #00F9FF y neutros.
 * Tipografía: Sora para títulos, Inter para texto.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    // El panel persiste `gaz2027-tema` y aplica `.dark` en <html>.
    darkMode: 'class',

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Sora', ...defaultTheme.fontFamily.sans],
            },

            colors: {
                // Tokens semánticos compartidos con la app móvil. Los valores
                // viven en `resources/css/app.css` para poder cambiar claro y
                // oscuro sin duplicar clases en cada vista.
                surface: 'rgb(var(--gz-surface) / <alpha-value>)',
                'surface-alt': 'rgb(var(--gz-surface-alt) / <alpha-value>)',
                'surface-elevated':
                    'rgb(var(--gz-surface-elevated) / <alpha-value>)',
                line: 'rgb(var(--gz-line) / <alpha-value>)',
                'line-strong': 'rgb(var(--gz-line-strong) / <alpha-value>)',
                content: 'rgb(var(--gz-content) / <alpha-value>)',
                'content-muted':
                    'rgb(var(--gz-content-muted) / <alpha-value>)',
                'content-subtle':
                    'rgb(var(--gz-content-subtle) / <alpha-value>)',
                // Morado de marca: 800 es el color principal (#310F38).
                brand: {
                    50: '#F7F2F9',
                    100: '#EEE1F2',
                    200: '#D9C1E2',
                    300: '#BC94CB',
                    400: '#9A62AE',
                    500: '#763D8C',
                    600: '#5A2270',
                    700: '#451754',
                    800: '#310F38',
                    900: '#230A29',
                    950: '#16051B',
                },

                // Cian de marca: 400 es el color exacto (#00F9FF).
                // Los tonos oscuros existen para texto cian sobre fondos claros.
                accent: {
                    50: '#E8FEFF',
                    100: '#C4FDFF',
                    200: '#8CFCFF',
                    300: '#4CFAFF',
                    400: '#00F9FF',
                    500: '#00D3D9',
                    600: '#00A6AB',
                    700: '#00787C',
                    800: '#004C4F',
                    900: '#00292B',
                },

                // Neutros fríos, para fondos y bordes.
                ink: {
                    50: '#F6F6F8',
                    100: '#ECECF1',
                    200: '#D7D8E0',
                    300: '#B4B6C4',
                    400: '#8B8EA1',
                    500: '#6B6E82',
                    600: '#53566A',
                    700: '#3E4052',
                    800: '#2A2C3A',
                    900: '#16171F',
                    950: '#0C0D12',
                },
            },

            boxShadow: {
                // Sombra suave con tinte morado, en vez de negro puro.
                card: '0 2px 8px rgba(49, 15, 56, 0.06), 0 16px 32px -8px rgba(49, 15, 56, 0.16)',
                floating: '0 4px 20px -2px rgba(22, 5, 27, 0.12), 0 16px 40px -6px rgba(22, 5, 27, 0.22)',
                focus: '0 0 0 3px rgba(0, 249, 255, 0.35)',
            },

            borderRadius: {
                xl: '1rem',
                '2xl': '1.25rem',
                '3xl': '1.5rem',
                'panel-sm': '10px',
                panel: '14px',
                'panel-lg': '18px',
                'panel-xl': '24px',
            },

            height: {
                'panel-header': '64px',
                'mobile-nav': '68px',
            },

            minHeight: {
                button: '44px',
                'mobile-button': '52px',
            },

            transitionDuration: {
                120: '120ms',
                200: '200ms',
                320: '320ms',
            },
        },
    },

    plugins: [forms],
};
