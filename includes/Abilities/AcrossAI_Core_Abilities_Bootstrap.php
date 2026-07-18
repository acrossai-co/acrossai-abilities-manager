<?php
/**
 * Orchestrator for the absorbed acrossai-core-abilities runtime.
 *
 * Wired from Main.php::define_public_hooks(). Two public entry points:
 * - register_category_callbacks( AcrossAI_Loader $loader ): adds 17 loader
 *   actions on wp_abilities_api_categories_init, one per Category_Registrar.
 * - register_abilities(): instantiates the 176 absorbed ability classes and
 *   runs the three companion-Main.php extras (Cron_Helpers::register_filter,
 *   Upload_Media chunk-sweep cron). Wired to plugins_loaded @ P20 matching
 *   the companion's original hook point.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities;

use AcrossAI_Abilities_Manager\Includes\AcrossAI_Loader;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Cron_Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap singleton for the absorbed ability inventory (Feature 046).
 */
final class AcrossAI_Core_Abilities_Bootstrap {

	/**
	 * Singleton reference.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — access via instance().
	 */
	private function __construct() {}

	/**
	 * Register the 17 category-registrar callbacks with the manager Loader.
	 *
	 * Called from Main.php::define_public_hooks() so every add_action
	 * literally traces back to Main.php (AC-HOOKS-MAIN literalism).
	 *
	 * @param AcrossAI_Loader $loader Manager hook loader.
	 * @return void
	 */
	public function register_category_callbacks( AcrossAI_Loader $loader ): void {
		$loader->add_action( 'wp_abilities_api_categories_init', Plugins\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Themes\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', FileManager\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Cache\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Database\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Users\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Block\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Settings\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Fonts\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Content\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Taxonomies\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Media\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Comments\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Menus\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Options\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Cron\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', SiteHealth\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Core\Category_Registrar::instance(), 'register' );
	}

	/**
	 * Instantiate the 176 absorbed ability classes so their inherited
	 * Ability_Definition constructor hooks acrossai_abilities_api_init.
	 *
	 * Also runs the three extras the companion Main.php ran alongside the
	 * ability instantiations: Cron_Helpers filter registration, the
	 * Upload_Media chunk-sweep hook, and the chunk-sweep cron scheduler.
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		// Default class_exists() autoloading is REQUIRED here — the composer
		// autoloader hasn't necessarily resolved Ability_Definition yet at
		// plugins_loaded @ P20. Passing false as the second arg would skip
		// autoload and cause a silent no-op (Feature 046 regression bug found
		// via the live Library page showing "No abilities registered yet"
		// while all 176 classes were on disk).
		if ( ! class_exists( '\AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition' ) ) {
			return;
		}

		new Plugins\Plugin_Activate();
		new Plugins\Plugin_Deactivate();
		new Plugins\Plugin_Install();
		new Plugins\Plugin_Update();
		new Plugins\Plugin_List();
		new Plugins\Update_Check();
		new Settings\Permalink_Get();
		new Settings\Permalink_Set();
		new Settings\Permalink_Flush();
		new Settings\Site_Title_Get();
		new Settings\Site_Title_Update();
		new Settings\Tagline_Get();
		new Settings\Tagline_Update();
		new Settings\Site_Logo_Update();
		new Settings\Site_Icon_Get();
		new Settings\Site_Icon_Update();
		new Themes\Theme_Activate();
		new Themes\Theme_Delete();
		new Themes\Theme_Install();
		new Themes\Theme_Update();
		new Themes\Theme_List();
		new Users\User_Get();
		new Users\User_List();
		new Users\User_Create();
		new Users\User_Update();
		new Users\User_Delete();
		new Users\User_Password_Reset();
		new Users\Roles_List();
		new Users\Role_Capabilities();
		new Cache\Cache_Flush();
		new Cache\Cache_Transient_Flush();
		new Cache\Cache_Rewrite_Flush();
		new Database\Schema_Extract();
		new Database\Db_Select();
		new Database\Db_Insert();
		new Database\Db_Update();
		new Database\Db_Delete();
		new Database\Tables_List();
		new Database\Db_Explain();
		new Database\Db_Stats();
		new Database\Db_Optimize();
		new FileManager\File_Read();
		new FileManager\File_Create();
		new FileManager\File_Edit();
		new FileManager\File_Delete();
		new Plugins\Plugin_Structure_Read();
		new Plugins\Plugin_Code_Read();
		new Plugins\Plugin_Files_Manage();
		new Themes\Theme_Structure_Read();
		new Themes\Theme_Code_Read();
		new Themes\Theme_Files_Edit();
		new FileManager\Wp_Config_Read();
		new FileManager\Wp_Config_Edit();
		new FileManager\Debug_Log_Read();
		new FileManager\Debug_Log_Clear();
		new FileManager\Zip_Create();
		new FileManager\Zip_Upload();
		new FileManager\Zip_Extract();
		new FileManager\Zip_Download();
		new FileManager\Zip_List();
		new FileManager\Zip_Delete();
		new Block\Pattern_List();
		new Block\Pattern_Read();
		new Block\Pattern_Create();
		new Block\Pattern_Update();
		new Block\Pattern_Delete();
		new Block\Template_List();
		new Block\Template_Read();
		new Block\Template_Create();
		new Block\Template_Update();
		new Block\Template_Delete();
		new Block\Global_Styles_List();
		new Block\Global_Styles_Read();
		new Block\Global_Styles_Create();
		new Block\Global_Styles_Update();
		new Block\Global_Styles_Delete();
		new Block\Theme_Json_Read();
		new Block\Theme_Json_Update();
		new Block\Block_Style_Variations_List();
		new Block\Block_Style_Variations_Read();
		new Block\Block_Style_Variations_Create();
		new Block\Block_Style_Variations_Update();
		new Block\Block_Style_Variations_Delete();
		new Block\Block_Info_List();
		new Block\Block_Info_Read();
		new Block\Template_Part_List();
		new Block\Template_Part_Read();
		new Block\Template_Part_Create();
		new Block\Template_Part_Update();
		new Block\Template_Part_Delete();
		new Fonts\Font_Family_List();
		new Fonts\Font_Family_Get();
		new Fonts\Font_Family_Create();
		new Fonts\Font_Family_Delete();
		new Fonts\Font_Face_List();
		new Fonts\Font_Face_Get();
		new Fonts\Font_Face_Create();
		new Fonts\Font_Face_Delete();
		new Content\Create_Post();
		new Content\Get_Post();
		new Content\Get_Post_Revisions();
		new Content\Get_Posts();
		new Content\Update_Post();
		new Content\Delete_Post();
		new Content\Get_Post_Meta();
		new Content\Update_Post_Meta();
		new Content\Create_Page();
		new Content\Get_Page();
		new Content\Get_Page_Revisions();
		new Content\Get_Pages();
		new Content\Update_Page();
		new Content\List_Post_Types();
		new Content\Create_Cpt_Item();
		new Content\Get_Cpt_Item();
		new Content\Get_Cpt_Item_Revisions();
		new Content\Get_Cpt_Items();
		new Content\Update_Cpt_Item();
		new Content\Delete_Cpt_Item();
		new Content\Get_Post_Translations();
		new Content\Set_Post_Language();
		new Content\Link_Post_Translation();
		new Content\Je_List_Options_Pages();
		new Content\Je_Get_Options_Page();
		new Content\Je_Update_Options_Page_Field();
		new Taxonomies\List_Taxonomies();
		new Taxonomies\Get_Taxonomy();
		new Taxonomies\Get_Cpt_Taxonomies();
		new Taxonomies\List_Terms();
		new Taxonomies\Get_Term();
		new Taxonomies\Create_Term();
		new Taxonomies\Update_Term();
		new Taxonomies\Delete_Term();
		new Taxonomies\Assign_Cpt_Terms();
		new Media\Upload_Media();
		new Media\Get_Media();
		new Media\List_Media();
		new Media\Update_Media();
		new Media\Delete_Media();
		new Media\Get_Media_Meta();
		new Media\Update_Media_Meta();
		new Media\Media_Mimes_List();
		new Media\Media_Mimes_Update();
		new Comments\Create_Comment();
		new Comments\Get_Comment();
		new Comments\List_Comments();
		new Comments\Update_Comment();
		new Comments\Delete_Comment();
		new Comments\Approve_Comment();
		new Comments\Unapprove_Comment();
		new Comments\Mark_As_Spam();
		new Comments\Get_Comment_Meta();
		new Comments\Update_Comment_Meta();
		new Menus\List_Menus();
		new Menus\Get_Menu();
		new Menus\Create_Menu();
		new Menus\Update_Menu();
		new Menus\Delete_Menu();
		new Menus\List_Menu_Items();
		new Menus\Get_Menu_Item();
		new Menus\Create_Menu_Item();
		new Menus\Update_Menu_Item();
		new Menus\Delete_Menu_Item();
		new Options\Get_Option();
		new Options\Update_Option();
		new Options\Delete_Option();
		new Options\List_Options();
		new Options\Search_Options();
		new Cron\Cron_List();
		new Cron\Cron_Get();
		new Cron\Cron_Next_Run();
		new Cron\Cron_Exists();
		new Cron\Cron_List_Schedules();
		new Cron\Cron_Get_Schedule();
		new Cron\Cron_Status();
		new Cron\Cron_Overdue();
		new Cron\Cron_Create();
		new Cron\Cron_Update();
		new Cron\Cron_Run_Now();
		new Cron\Cron_Create_Schedule();
		new Cron\Cron_Delete();
		new Cron\Cron_Delete_All_By_Hook();
		new Cron\Cron_Delete_Schedule();
		new SiteHealth\Site_Health_Status();
		new SiteHealth\Site_Health_Info();
		new Core\Wp_Core_Update_Check();
		new Core\Wp_Core_Update();

		// Extras the companion Main.php also ran alongside the ability
		// instantiations. See docs/planning/046-absorb-core-abilities-into-manager.md
		// CHANGE-4c.
		Cron_Helpers::register_filter();
		add_action( Media\Upload_Media::CHUNK_SWEEP_HOOK, array( Media\Upload_Media::class, 'sweep_chunk_sessions' ) );
		Media\Upload_Media::register_sweep_cron();

		// Feature 041: Zip_Upload chunk sweeper — same shape as Upload_Media.
		add_action( FileManager\Zip_Upload::CHUNK_SWEEP_HOOK, array( FileManager\Zip_Upload::class, 'sweep_chunk_sessions' ) );
		FileManager\Zip_Upload::register_sweep_cron();
	}
}
