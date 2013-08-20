<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2005 Kasper Skaarhoj (kasperYYYY@typo3.com)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * This is the MAIN DOCUMENT of the TypoScript driven standard front-end (from the "cms" extension)
 * Basically this is the "index.php" script which all requests for TYPO3 delivered pages goes to in the frontend (the website)
 *
 * $Id: index.php 1421 2006-04-10 09:27:15Z mundaun $
 *
 * @author	Daniel Wegener <avithan@googlemail.com>
 * @package TYPO3
 *
 */




class google_translate_content_processCmdmapClass extends t3lib_svbase {

    protected $googleTranslateKey = '';
    const ENDPOINT = 'https://www.googleapis.com/language/translate/v2';


	function processCmdmap_preProcess(&$command, $table, $id, $value, $caller) {
		$this->conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['google_translate_content']);

		if (strlen($this->conf['googleApiKey'])<=0 || $table != 'tt_content'){
			return;
		}
		$this->googleTranslateKey = $this->conf['googleApiKey'];
			// If we are on to translate something, we handle the translation here and unset the command afterwards to avoid standard translation processing.
		if ($command == 'localize') {
			$command = '';
			$shoulProcessNormalTranslation = $this->localize($table,$id,$value,$caller);
			if($shoulProcessNormalTranslation === TRUE) {
				$command = 'localize';
			}
		}
	}
	
	
	/**
	 * Localizes a record to another system language
	 * In reality it only works if transOrigPointerTable is not set. For "pages" the implementation is hardcoded
	 *
	 * @param	string		Table name
	 * @param	integer		Record uid (to be localized)
	 * @param	integer		Language ID (from sys_language table)
	 * @return	void
	 */
	function localize($table,$uid,$language,$caller) {
		global $TCA;
        global $TYPO3_DB;

		$uid = intval($uid);

		if ($TCA[$table] && $uid)	{
			t3lib_div::loadTCA($table);

			if (($TCA[$table]['ctrl']['languageField'] && $TCA[$table]['ctrl']['transOrigPointerField'] && !$TCA[$table]['ctrl']['transOrigPointerTable']) || $table==='pages')	{
				if ($langRec = t3lib_BEfunc::getRecord('sys_language',intval($language),'uid,title,static_lang_isocode'))	{

					//$staticInfo->init();
					//$staticInfo = t3lib_div::makeInstance('tx_staticinfotables_pi1');
					$isoCode = t3lib_BEfunc::getRecord('static_languages',intval($langRec['static_lang_isocode']),'lg_iso_2');

					foreach($isoCode as $key=>$val) {
						$langRec[$key] = $val;
					}

					if ($caller->doesRecordExist($table,$uid,'show'))	{

						$row = t3lib_BEfunc::getRecordWSOL($table,$uid);	// Getting workspace overlay if possible - this will localize versions in workspace if any


                        if (is_array($row))	{
							if ($row[$TCA[$table]['ctrl']['languageField']] <= 0 || $table==='pages')	{
								if ($row[$TCA[$table]['ctrl']['transOrigPointerField']] == 0 || $table==='pages')	{
									if ($table==='pages')	{
										$pass = $TCA[$table]['ctrl']['transForeignTable']==='pages_language_overlay' && !t3lib_BEfunc::getRecordsByField('pages_language_overlay','pid',$uid,' AND '.$TCA['pages_language_overlay']['ctrl']['languageField'].'='.intval($langRec['uid']));
										$Ttable = 'pages_language_overlay';
										t3lib_div::loadTCA($Ttable);
									} else {
										$pass = !t3lib_BEfunc::getRecordsByField($table,$TCA[$table]['ctrl']['transOrigPointerField'],$uid,'AND pid='.intval($row['pid']).' AND '.$TCA[$table]['ctrl']['languageField'].'='.intval($langRec['uid']));
										$Ttable = $table;
									}

									if ($pass)	{
										list($tscPID) = t3lib_BEfunc::getTSCpid($table,$uid,'');
										$TSConfig = $caller->getTCEMAIN_TSconfig($tscPID);
										// Detault: take the language of the be_user as translate-from language
										$ln_iso2_from = $caller->BE_USER->user['lang'];

										// If there is a defaultLanguageCode in the PageTSConfig, take it.
										if (strlen($TSConfig['defaultLanguageCode'])){
											$ln_iso2_from = $TSConfig['defaultLanguageCode'];
                                        }
										


										$ln_iso2_to = strtolower($langRec['lg_iso_2']);
										
										//break if we try to translate one language to itself
										if (strtolower($ln_iso2_from) == strtolower($ln_iso2_to) && strlen($ln_iso2_from)) {
											//$caller->newlog('Localization failed; There is no need to translate from "'.$ln_iso2_from.'" to "'.$ln_iso2_to.'" !',1);
											//process normal translation
											return true;
										}




										$success = false;
                                        $toTranslate  = array();


                                        switch($row['CType']){

                                            case "text":
                                                $recordTranslted = $this->translatePageContent($row, $ln_iso2_from, $ln_iso2_to,$langRec['uid']);
                                                break;
                                            case "textpic":
                                                $recordTranslted = $this->translatePageContent($row, $ln_iso2_from, $ln_iso2_to,$langRec['uid']);
                                                break;

                                            case "image":
                                                $recordTranslted = $this->translateImage($row, $ln_iso2_from, $ln_iso2_to,$langRec['uid']);
                                                break;
                                            case "bullets":
                                                $recordTranslted = $this->translatePageContent($row, $ln_iso2_from, $ln_iso2_to,$langRec['uid']);
                                                break;
                                            case "table":
                                                $recordTranslted = $this->translatePageContent($row, $ln_iso2_from, $ln_iso2_to,$langRec['uid']);
                                                break;
                                            default:
                                                return true;

                                        }




                                        $success = $recordTranslted['success'];
                                        $overrideValues = $recordTranslted['data'];




										if (!$success) {
											//$caller->newlog("Couldn't translate this record with google service" ,1);
											return TRUE;
										}

										if ($Ttable === $table)	{
                                            $res = $TYPO3_DB->exec_INSERTquery($table,$overrideValues,$no_quote_fields = FALSE);

                                            if($row['CType'] == "textpic"){
                                                if($row['image']){
                                                    $arrItem = array();
                                                    $newValueArr = array();
                                                    $finalArr = array();
                                                    $inserId = $TYPO3_DB->sql_insert_id();
                                                    $uid = $row['uid'];
                                                    $uqery = $TYPO3_DB->exec_SELECTquery('*','sys_file_reference',"uid_foreign=$uid and deleted=0 and hidden=0",'','','');
                                                    while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($uqery)) {

                                                        $arrItem[] = $row;
                                                    }

                                                    //remove old key
                                                    if(empty($arrItem) == false){

                                                        foreach($arrItem as $key=>$value){
                                                            $uid = $value['uid'];
                                                            foreach($value as $key2=>$value2){
                                                                if($key2 == "uid" || $key2 =="sys_language_uid" || $key2 == "uid_foreign"){
                                                                    $newValueArr[$key2];
                                                                }else{
                                                                    $newValueArr[$key][$key2] = $value2;
                                                                }

                                                            }
                                                            $newValueArr[$key]['sys_language_uid'] = $langRec['uid'];
                                                            $newValueArr[$key]['uid_foreign'] = $inserId;
                                                            $newValueArr[$key]['l10n_parent'] =  $uid;
                                                        }

                                                        //save

                                                        foreach($newValueArr as $key=> $value){
                                                            $res = $TYPO3_DB->exec_INSERTquery('sys_file_reference',$value,$no_quote_fields = FALSE);
                                                        }


                                                    }


                                                }
                                            }
										} else {

												// Create new record:
											$copyTCE = t3lib_div::makeInstance('t3lib_TCEmain');
											$copyTCE->stripslashes_values = 0;
											$copyTCE->cachedTSconfig = $this->cachedTSconfig;	// Copy forth the cached TSconfig
											$copyTCE->dontProcessTransformations=1;		// Transformations should NOT be carried out during copy

											$copyTCE->start(array($Ttable=>array('NEW'=>$overrideValues)),'',$this->BE_USER);
											$copyTCE->process_datamap();

												// Getting the new UID as if it had been copied:
											$theNewSQLID = $copyTCE->substNEWwithIDs['NEW'];
											if ($theNewSQLID)	{
													// If is by design that $Ttable is used and not $table! See "l10nmgr" extension. Could be debated, but this is what I chose for this "pseudo case"
												$this->copyMappingArray[$Ttable][$uid] = $theNewSQLID;
											}
										}
									} else {$caller->newlog('Localization failed; There already was a localization for this language of the record!',1); return TRUE;}
								} else {$caller->newlog('Localization failed; Source record contained a reference to an original default record (which is strange)!',1); return TRUE;}
							} else {$caller->newlog('Localization failed; Source record had another language than "Default" or "All" defined!',1); return TRUE;}
						} else {$caller->newlog('Attempt to localize record that did not exist!',1); return TRUE;}
					} else {$caller->newlog('Attempt to localize record without permission',1);  return TRUE;}
				} else {$caller->newlog('Sys language UID "'.$language.'" not found valid!',1); return TRUE;}
			} else {$caller->newlog('Localization failed; "languageField" and "transOrigPointerField" must be defined for the table!',1); return TRUE;}
		}
	}




    function translateText($text, $from, $to){

        $values = array(
            'key'    => $this->googleTranslateKey,
            'source' => $from,
            'target' => $to,
            'q'      => $text
        );

        // turn the form data array into raw format so it can be used with cURL
        $formData = http_build_query($values, '', '&');

        // create a connection to the API endpoint
        $ch = curl_init(self::ENDPOINT);

        // tell cURL to return the response rather than outputting it
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // write the form data to the request in the post body
        curl_setopt($ch, CURLOPT_POSTFIELDS, $formData);

        // include the header to make Google treat this post request as a get request
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-HTTP-Method-Override: GET'));

        // execute the HTTP request
        $json = curl_exec($ch);

        curl_close($ch);

        // decode the response data
        $data = json_decode($json, true);

        return $data['data']['translations'][0]['translatedText'];

    }

    function splitText($text){
        $lenght = 0 ;
        $lengTemp = 0;
        $wordKey = array();
        $result = preg_split('/(?<=[.])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach($result as $key=>$value){
            $lengTemp =  strlen($value);
            $lenght +=  $lengTemp;

            if($lenght > 5000){
                $wordKey[] = $key -1;
                $lenght = 0;

            }

        }
        return $wordKey;
    }


    public function getItemArray($wordKey,$textArr,$count){

        $first = true;
        $nextItem = 0;
        $supportArrayToTranslate = array();
        foreach($wordKey as $key=>$value){
            if($first){
                $supportArrayToTranslate[] = implode("",array_slice($textArr,0,$value));
                $first = FALSE;
                $nextItem = $value +1;

            }else{
                $supportArrayToTranslate[] = implode("",array_slice($textArr,$nextItem,$value));
                $nextItem = $value +1;
            }
        }

        if(end($wordKey) !=  $count){
            $supportArrayToTranslate[] = implode("",array_slice($textArr,(end($wordKey)+1),$count));
        }



        return $supportArrayToTranslate;

    }



    public function translatePageContent($row, $from, $ln_iso2_to,$lang){

        $arrayResult = array();
        $arrayResult['success'] = false;
        $wordKey = $this->splitText($row['bodytext']);

        if(empty($wordKey)){
            $texttotranslate = $this->translateText($row['bodytext'], $from,$ln_iso2_to);

            if($texttotranslate){
                $arrayResult['success'] = true;

                foreach($row as $key=>$value){
                    if($key != "uid"){

                        $overrideValues[$key] = $value;
                    }
                }

                if($row['header']){
                    $overrideValues['header'] = $this->translateText($row['header'], $from, $ln_iso2_to);
                }
                $overrideValues['bodytext'] = $texttotranslate;
                $overrideValues['sys_language_uid'] = $lang;
                $overrideValues['l18n_parent'] = $row['uid'];
                $overrideValues['pid'] = $row['pid'];
                $overrideValues['hidden'] = 1;

                $arrayResult['data'] = $overrideValues;

                return $arrayResult;
            }

        }else{
            $count = count( preg_split('/(?<=[.])\s+/', $row['bodytext'], -1, PREG_SPLIT_NO_EMPTY));
            $textArr = preg_split('/(?<=[.])\s+/', $row['bodytext'], -1, PREG_SPLIT_NO_EMPTY);
            $data = $this->getItemArray($wordKey,$textArr,$count);

            $bodyText = '';

            foreach($data as $value){
                $bodyText.= $this->translateText($value, $from, $ln_iso2_to);
            }

            if($bodyText){
                $arrayResult['success'] = true;
            }

            foreach($row as $key=>$value){
                if($key != "uid"){
                    $overrideValues[$key] = $value;
                }
            }

            $overrideValues['bodytext'] = $bodyText;

            if($row['header']){
                $overrideValues['header'] = $this->translateText($row['header'], $from,$ln_iso2_to);
            }

            $overrideValues['sys_language_uid'] = $lang;
            $overrideValues['l18n_parent'] = $row['uid'];
            $overrideValues['pid'] = $row['pid'];
            $overrideValues['hidden'] = 1;

            $arrayResult['data'] = $overrideValues;
            return $arrayResult;

        }
    }


    public function translateImage($row,  $from, $ln_iso2_to, $lang) {
        $arrayResult = array();
        $arrayResult['success'] = false;
        $overrideValues = array();
        foreach($row as $key=>$value){
            if($key != "uid"){
                $overrideValues[$key] = $value;
            }
        }

        if($row['header']){
            $overrideValues['header'] = $this->translateText($row['header'], $from, $ln_iso2_to);
            if($overrideValues['header']){
                $arrayResult['success'] = true;
            }

        }else{
            $arrayResult['success'] = true;
        }

        $overrideValues['sys_language_uid'] = $lang;
        $overrideValues['l18n_parent'] = $row['uid'];
        $overrideValues['pid'] = $row['pid'];
        $overrideValues['hidden'] = 1;


        $arrayResult['data'] = $overrideValues;

        return $arrayResult;

    }
}

if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/google_translate_content/hooks/class.google_translate_content_processCmdmapClass.php"]) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/google_translate_content/hooks/class.google_translate_content_processCmdmapClass.php"]);
}

?>