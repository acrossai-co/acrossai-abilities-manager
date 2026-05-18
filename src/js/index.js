/**
 * Logger Admin UI Build Entry Point
 *
 * Imports and registers the LogsTable React component.
 * Compiled via @wordpress/scripts build pipeline.
 *
 * @since 0.1.0
 */

// Import React component
import LogsTable from './components/LogsTable';

// Import stylesheet
import '../scss/logs-table.scss';

// Export component for use in admin page
window.AcrossAILoggerUI = {
	LogsTable,
};
