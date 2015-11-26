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
    public function writeCustomLog($msg, $path = null) {
        if($path == null){
            $path = Mage::getBaseDir('log').'/customimport.log';
        }
        error_log("[".date('Y:m:d H:i:s', time())."] : ".print_r($msg, true)."<br/> \r\n", 3, $path);            
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
	
	public function reportInfo($msg){
		$msg = '<span style="color:blue;">'.$msg.'</span>';
		$this->writeCustomLog($msg);
		echo "<br/><br/>".$msg;
	}
	
	public function reportError($msg){
		$msg = '<span style="color:red;">'.$msg.'</span>';
		$this->writeCustomLog($msg);
		echo "<br/><br/>".$msg;
	}
	
	public function reportSuccess($msg){
		$msg = '<span style="color:#009900;">'.$msg.'</span>';
		$this->writeCustomLog($msg);
		echo "<br/><br/>".$msg;
	}
}
