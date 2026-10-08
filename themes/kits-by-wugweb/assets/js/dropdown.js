/**
 * Kits dropdown (filters and Sort) on the WordPress Interactivity API — the same system as the core Navigation block.
 * One open at a time; Esc or a click outside closes it and returns focus to its button; arrow keys move through the
 * options. Server-rendered with directives (kits_filter_dropdown, kits_sort_dropdown).
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

const { state } = store( 'kits/dropdown', {
	state: { openId: '' },
	actions: {
		toggle() {
			const ctx = getContext();
			state.openId = state.openId === ctx.id ? '' : ctx.id;
			if ( state.openId === ctx.id ) {
				const { ref } = getElement();
				setTimeout( () => ref.closest( '.kit-dd' )?.querySelector( '.kit-dd-panel input, .kit-dd-panel a' )?.focus(), 60 ); // after the panel is shown
			}
		},
		keys( event ) {
			const ctx = getContext();
			const { ref } = getElement();
			if ( event.key === 'Escape' && state.openId === ctx.id ) {
				state.openId = '';
				ref.querySelector( '.kit-dd-btn' )?.focus();
				return;
			}
			if ( event.key !== 'ArrowDown' && event.key !== 'ArrowUp' ) return;
			event.preventDefault();
			const items = [ ...ref.querySelectorAll( '.kit-dd-panel input, .kit-dd-panel a' ) ];
			if ( ! items.length ) return;
			if ( state.openId !== ctx.id ) { state.openId = ctx.id; requestAnimationFrame( () => items[ 0 ].focus() ); return; }
			const i = items.indexOf( document.activeElement );
			items[ ( i + ( event.key === 'ArrowDown' ? 1 : -1 ) + items.length ) % items.length ].focus();
		},
		outside( event ) {
			const ctx = getContext();
			const { ref } = getElement();
			if ( state.openId === ctx.id && ! ref.contains( event.target ) ) state.openId = '';
		},
	},
	callbacks: {
		isOpen() { return state.openId === getContext().id; },
	},
} );
