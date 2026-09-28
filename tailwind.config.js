import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
    	extend: {
    		fontFamily: {
    			sans: [
    				'Figtree',
                    ...defaultTheme.fontFamily.sans
                ],
    			serif: [
    				'Lora',
                    ...defaultTheme.fontFamily.serif
                ]
    		},
    		borderRadius: {
    			lg: 'var(--radius)',
    			md: 'calc(var(--radius) - 2px)',
    			sm: 'calc(var(--radius) - 4px)'
    		},
    		colors: {
    			background: 'hsl(var(--background))',
    			foreground: 'hsl(var(--foreground))',
    			card: {
    				DEFAULT: 'hsl(var(--card))',
    				foreground: 'hsl(var(--card-foreground))'
    			},
    			popover: {
    				DEFAULT: 'hsl(var(--popover))',
    				foreground: 'hsl(var(--popover-foreground))'
    			},
    			primary: {
    				DEFAULT: 'hsl(var(--primary))',
    				foreground: 'hsl(var(--primary-foreground))'
    			},
    			secondary: {
    				DEFAULT: 'hsl(var(--secondary))',
    				foreground: 'hsl(var(--secondary-foreground))'
    			},
    			muted: {
    				DEFAULT: 'hsl(var(--muted))',
    				foreground: 'hsl(var(--muted-foreground))'
    			},
    			accent: {
    				DEFAULT: 'hsl(var(--accent))',
    				foreground: 'hsl(var(--accent-foreground))'
    			},
    			destructive: {
    				DEFAULT: 'hsl(var(--destructive))',
    				foreground: 'hsl(var(--destructive-foreground))'
    			},
    			border: 'hsl(var(--border))',
    			input: 'hsl(var(--input))',
    			ring: 'hsl(var(--ring))',
    			chart: {
    				'1': 'hsl(var(--chart-1))',
    				'2': 'hsl(var(--chart-2))',
    				'3': 'hsl(var(--chart-3))',
    				'4': 'hsl(var(--chart-4))',
    				'5': 'hsl(var(--chart-5))'
    			},
    			brand: {
    				header: {
    					DEFAULT: 'var(--brand-header-bg)',
    					text: 'var(--brand-header-text)',
    					muted: 'var(--brand-header-text-muted)'
    				},
    				accent: {
    					DEFAULT: 'var(--brand-accent)',
    					hover: 'var(--brand-accent-hover)'
    				},
    				page: 'var(--brand-page-bg)',
    				card: 'var(--brand-card-bg)',
    				text: {
    					primary: 'var(--brand-text-primary)',
    					secondary: 'var(--brand-text-secondary)'
    				}
    			}
    		}
    	}
    },

    // `forms` je bio uvezen ali NIKAD dodat ovde (pre-postojeći propust,
    // otkriven pri stilizovanju "Samo na stanju" checkbox-a u terakoti —
    // `text-brand-accent` na checkbox-u je zavisio od ovog plugin-a i tiho
    // nije imao efekta, checkbox je ostajao na browser-default plavoj boji).
    // `strategy: 'class'` (NE podrazumevano 'base'!) — 'base' bi globalno
    // resetovao IZGLED svakog <input>/<select>/<textarea> na celom sajtu
    // (admin forme, Breeze auth, checkout...), daleko van opsega ovog
    // dizajna filter panela. 'class' čini reset opt-in preko `form-*`
    // klasa (npr. `form-checkbox`) — nula uticaja bilo gde drugde dok se
    // eksplicitno ne doda.
    plugins: [forms({ strategy: 'class' })],
};
