<?php
/**
#    Copyright (C) 2013-2015 Global Era Commerce (http://www.globalera.com). All Rights Reserved
#    @author Serenus Infotech <magento@serenusinfotech.com>
#    @author Intelliant <magento@intelliant.net>
#
#    This program is free software: you can redistribute it and/or modify
#    it under the terms of the GNU Affero General Public License as
#    published by the Free Software Foundation, either version 3 of the
#    License, or (at your option) any later version.
#
#    This program is distributed in the hope that it will be useful,
#    but WITHOUT ANY WARRANTY; without even the implied warranty of
#    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#    GNU Affero General Public License for more details.
#
#    You should have received a copy of the GNU Affero General Public License
#    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 **/

class Gec_Customimport_Helper_Data extends Mage_Core_Helper_Abstract
{
	public static $colormap = array(
			"info" => "blue",
			"error" => "red",
			"success" => "#009900"
	);
	public static $is_verbose = true;
	public function isVerbose() {
		return self::$is_verbose;
	}
	public function getColorMap() {
		return self::$colormap;
	}
	public function verboseLog($msg)
	{
		$this->writeCustomLog($msg);
// 		if(Gec_Customimport_Helper_Data::isVerbose()){
// 			$this->writeCustomLog($msg);
// 		}
	}

    public function writeCustomLog($msg, $path = null) {
    	if($path == null){
    		$path = Mage::getBaseDir('log').'/customimport.log';
    	}
    	error_log("[".date('Y:m:d H:i:s', time())."] : ".print_r($msg, true)."\n", 3, $path);
    }
    
    public function sendLogEmail($logPath = '')
    {
		$logPath = Mage::getBaseDir('log').'/customimport.log';
        $logMessage = file_get_contents($logPath);
        if($logMessage) {                        
            $finalImportStatus = null;
            $logSubject = 'Catalog Import Report - '.$_SERVER['SERVER_NAME'].' - '.date('Y:m:d H:i:s T', time());
            $emailTemplate = Mage::getModel('core/email_template')->loadDefault('import_status');
            $senderName = Mage::getStoreConfig('trans_email/ident_general/name');
            $senderEmail = Mage::getStoreConfig('trans_email/ident_general/email');
            $emailTemplateVariables = array();
            $emailTemplateVariables['msgcol'] = $logMessage;
            $emailTemplateVariables['finalstatus'] = $finalImportStatus;
            $processedTemplate = $emailTemplate->getProcessedTemplate($emailTemplateVariables);
            $reciverEmail= Mage::getStoreConfig('trans_email/ident_custom1/email');
            $mail = Mage::getModel('core/email')
                    ->setToName($senderName)
                    ->setToEmail($reciverEmail)
                    ->setBody($processedTemplate)
                    ->setSubject($logSubject)
                    ->setFromEmail($senderEmail)
                    ->setFromName($senderName)
                    ->setType('html');
            try{
                $mail->send();
            }
            catch(Exception $error)
            {
                Mage::getSingleton('core/session')->addError($error->getMessage());
                return false;
            }    
        } else {
            Mage::getSingleton('core/session')->addError('there were no log report generated...!');
                return false;
        }
            
    }
    
    public function getCurrentLocaleDateTime($defaultUTCDate)
    {
        return date('Y-m-d H:i:s', Mage::getModel('core/date')->timestamp(strtotime($defaultUTCDate))); 
    }
    private function reportLog($message, $color="black", $display = true)
    {
    	$this->writeCustomLog($message);
    	if($display)
    	{
	    	$message = sprintf('<br/><br/><span style="color:%s;">%s</span>', $color, $message);
	    	echo $message;
    	}
    }
    public function reportStart($msg){
    	$msg = sprintf('******************** Starting %s ********************', $msg);
    	$this->reportLog($msg);
    }
    public function reportEnd($msg){
    	$msg = sprintf('******************** Ending %s ********************', $msg);
    	$this->reportLog($msg);
    }
	public function reportInfo($msg){
		$this->reportLog($msg, Gec_Customimport_Helper_Data::getColorMap()['info']);
	}
	
	public function reportError($msg){
		$this->reportLog($msg, Gec_Customimport_Helper_Data::getColorMap()['error']);
	}
	
	public function reportSuccess($msg){
		$this->reportLog($msg, Gec_Customimport_Helper_Data::getColorMap()['success']);
	}
}
