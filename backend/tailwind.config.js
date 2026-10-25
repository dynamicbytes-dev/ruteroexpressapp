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

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Sora', ...defaultTheme.fontFamily.sans],
            },

            colors: {
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
                card: '0 1px 2px rgba(49, 15, 56, 0.06), 0 8px 24px -12px rgba(49, 15, 56, 0.24)',
                focus: '0 0 0 3px rgba(0, 249, 255, 0.35)',
            },

            borderRadius: {
                xl: '0.875rem',
                '2xl': '1.125rem',
            },
        },
    },

    plugins: [forms],
};
