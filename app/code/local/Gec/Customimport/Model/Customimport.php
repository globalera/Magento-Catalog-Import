<?php
/**
#    Copyright (C) 2013 Global Era (http://www.globalera.com). All Rights Reserved
#    @author Serenus Infotech <magento@serenusinfotech.com>
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

class Gec_Customimport_Model_Customimport extends Mage_Core_Model_Abstract {	
    public function _construct()   {
        parent::_construct();
        $this->_init('customimport/customimport');
    }
    
    /*to fetch the default set id by external set id*/
    public function getAttributeSetIdByExternalId($externalId){  // works   	
        $attrsetmappingCollection = Mage::getModel('customimport/attrsetmapping')
                                   ->getCollection()
                                   ->addFieldToFilter('external_id', array('eq' => $externalId));                                   
        return $attrsetmappingCollection->getFirstItem()->getMagentoId();
    }

    /*to insert a row for external id and default id of attribute set*/    	  
	public function mapAttributeSet($externalId, $magentoId){
		$attrsetmapping = Mage::getModel('customimport/attrsetmapping');
        $data = array('external_id' => $externalId, 'magento_id' => $magentoId);
        $attrsetmapping->setData($data);
        $attrsetmapping->save();
	  }
	  
	  /*to check if attribute group exists in attribute set*/
    public function getAttributeGroupByExternalId($externalId, $attributeSetId){
    	$attrgroupCollection = Mage::getModel('customimport/attrgroup')
                              ->getCollection()
                              ->addFieldToFilter('external_id', array('eq' => $externalId))
                              ->addFieldToFilter('attribute_set_id', array('eq' => $attributeSetId));
                              
         return $attrgroupCollection->getFirstItem()->getMagentoId();
    }
	  /*to insert a row to define relation between external id ,magento id of group along with setid*/
     	  
	public function mapAttributeGroup($externalId, $magentoId, $attributeSetId){
        $attrgroup = Mage::getModel('customimport/attrgroup');
        $attrgroup->setData(
            array('external_id' => $externalId,
                'magento_id' => $magentoId,
                'attribute_set_id' => $attributeSetId
            ))->save();
	  }	  	  
	  	/*to get default group id from attribute group table using set id*/	  	  
	  public function getGroupIdUsingSetId($attribute_group_name, $attributeSetId){
          $eavattributegroupCollection = Mage::getModel('customimport/eavattributegroup')
                                        ->getCollection()
                                        ->addFieldToFilter('attribute_group_name', array('eq' => $attribute_group_name))
                                        ->addFieldToFilter('attribute_set_id', array('eq' => $attributeSetId));
          return $eavattributegroupCollection->getFirstItem()->getAttributeGroupId();
	  }

	 public function isAttributeExistsInGroup($attribute_id, $attributeGroupId){
           $eaventityattributeCollection = Mage::getModel('customimport/eaventityattribute')
                                         ->getCollection()
                                         ->addFieldToFilter('attribute_group_id', array('eq' => $attributeGroupId))
                                         ->addFieldToFilter('attribute_id', array('eq' => $attribute_id));
           return $eaventityattributeCollection->getFirstItem()->getEntityAttributeId();
	  }  
	  
 	public function updateSequenceOfAttribute($attributeGroupId, $attribute_id, $attribute_sort_order){
          $eaventityattribute = Mage::getModel('customimport/eaventityattribute');
          $eaventityattributeCollection = $eaventityattribute
                                          ->getCollection()
                                          ->addFieldToFilter('attribute_group_id', array('eq' => $attributeGroupId))
                                          ->addFieldToFilter('attribute_id', array('eq' => $attribute_id));
          $id = $eaventityattributeCollection->getFirstItem()->getEntityAttributeId();
          $data = array('sort_order' => $attribute_sort_order);
          if($id){
	          $eaventityattribute->load($id)->addData($data);
	          try {
	              $eaventityattribute->setEntityAttributeId($id)->save();
	              echo "Data updated successfully.";
	
	          } catch (Exception $e){
	              echo $e->getMessage();
	          }
          }
    	}  
    	  
    public function isOptionExistsInAttribute($external_id, $attributeId){
        $eavattributeoptionCollection = Mage::getModel('customimport/eavattributeoption')->getCollection()
                                        ->addFieldToFilter('attribute_id',array('eq' => $attributeId))
                                        ->addFieldToFilter('externalid',array('eq' => $external_id));
       return $eavattributeoptionCollection->getFirstItem()->getOptionId();
    }   
    
 	public function isSubcategoryExists($external_subcat_id, $parent_id){ 
          $externalcatCollection = Mage::getModel('customimport/externalcategorymappinginfo')
                                                  ->getCollection()
                                                  ->addFieldToFilter('external_id', array('eq' => $external_subcat_id))
                                                  ->addFieldToFilter('magento_pid', array('eq' => $parent_id));
          return $externalcatCollection->getFirstItem()->getMagentoId();
    }   
    
    public function isCategoryExists($external_subcat_id){
    	 $externalcatCollection = Mage::getModel('customimport/externalcategorymappinginfo')
                                 	->getCollection()
                                	->addFieldToFilter('external_id', array('eq' => $external_subcat_id)); 
       return $externalcatCollection->getFirstItem()->getMagentoId();
        
    }  
      
   public function updateCategoryMappingInfo($externalId, $magentoId, $externalParentId, $parentId){
        $externalcatmappinginfo = Mage::getModel('customimport/externalcategorymappinginfo');
        $externalcatmappinginfo->setData(
            array('external_id' => $externalId,
                'magento_id' => $magentoId,
                'external_pid' => $externalParentId,
                'magento_pid' => $parentId
            ))->save();
    }
    
   public function updateParent($parentId,$categoryId){
    	 $data = array('parent_id' => $parentId);
         $catalogentity = Mage::getModel('customimport/catalogentity')->load($categoryId)->addData($data);
         try{
         	$catalogentity->setEntityId($categoryId)->save();
          } catch (Exception $e){
              echo $e->getMessage();
          }
    }
}
