<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');


	
require_once(t3lib_extMgm::extPath('google_translate_content').'/hooks/class.google_translate_content_processCmdmapClass.php');

$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][]='google_translate_content_processCmdmapClass';
	
?>