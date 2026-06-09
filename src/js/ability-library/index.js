/**
 * Entry point for the Ability Library admin page React app.
 *
 * @since 0.1.0
 */
import { createRoot } from '@wordpress/element';
import LibraryPage from './components/LibraryPage';

const rootEl = document.getElementById('acrossai-library-root');
if (rootEl) {
	createRoot(rootEl).render(<LibraryPage />);
}
