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

class Gec_Customimport_Block_Customimport extends Gec_Customimport_Block_Catalogimport
{ 
    public function parseXml($xmlPath){
    	$this->_store_id = Mage::app()->getWebsite()->getDefaultGroup()->getDefaultStoreId();
    	$this->_default_category_id = Mage::app()->getStore()->getRootCategoryId();  	
        $xmlObj = new Varien_Simplexml_Config($xmlPath);
        $this->_xmlObj = $xmlObj;
    }

    public function showCategory(){
        $xmlObj =  $this->_xmlObj;
        $xmlData = $xmlObj->getNode();

        if( $xmlData->categories->category instanceof Varien_Simplexml_Element)
        {
            return $xmlData->categories->category;
        }
        else{
            return false;
        }
    }

    public function countAll(){
        $xmlObj =  $this->_xmlObj;
        $xmlData = $xmlObj->getNode();
      //  $this->_category_list = $xmlData->categories->category;
     	$this->_category_list = $xmlData->productAssociations->association;
        $this->lookup($this->_category_list );
    }

    public function countCategory(){
        $xmlObj =  $this->_xmlObj;
        $xmlData = $xmlObj->getNode();
        $this->_category_list = $xmlData->categories->category;
        return $this->lookup($this->_category_list );
    }

    public function countProduct(){
        $xmlObj =  $this->_xmlObj;
        $xmlData = $xmlObj->getNode();
        $this->_product_list = $xmlData->products->product;
        return $this->lookup($this->_product_list);
    }

    public function showProducts(){
    	$xmlObj =  $this->_xmlObj;
        $xmlData = $xmlObj->getNode();
        $this->_product_list = $xmlData->products->product;
        if( $this->_product_list instanceof Varien_Simplexml_Element){
            return $this->_product_list;
        }else{
            return false;
        }
    }

    public function reindexDB($val){
        $process = Mage::getModel('index/process')->load($val);
        $process->reindexAll();
    }

    public function importAllProducts($products){
        $item = array();
        foreach($products as $product){
            $this->_current_row++;
            $this->importItem($product);
        }
        $this->createLog("Successfully created products: {$this->_created_num}");
        $this->createLog("Successfully updated products: {$this->_updated_num}");
        $this->_created_num = 0;
        $this->_updated_num = 0;
    }

    public function importAllCategory($categories){
        $this->_created_num = 0;
        $this->_updated_num = 0;
        foreach($categories  as $category){
            $this->importCategory($category);
        }
        $this->createLog("Successfully created categories: {$this->_created_num}");
        $this->createLog("Successfully updated categories: {$this->_updated_num}");
        Mage::log("Successfully created categories: {$this->_created_num} Successfully updated categories: {$this->_updated_num}",null,'mylog.log');
 	    $this->_created_num = 0;
        $this->_updated_num = 0;
    }

    public function parseAllCategoryRelation(){
        $xmlObj =  $this->_xmlObj;
        $xmlData = $xmlObj->getNode();
        $this->_cat_relation = $xmlData->categoryRelations->categoryRelation;

        foreach($this->_cat_relation as $catRelation){
            $parent = (string)$catRelation->parentId;
            $externall = $this->checkExternalId($parent);
            if($externall){				  //check if parent id exists.
                if(count($externall) == 1){
                    reset($externall); //to take 1st key of array
                    $first_key = key($externall);
                    foreach($catRelation->subCategory as $sub){
                        $this->updateCategoryRelation($sub,$first_key,$parent);
                    }
                }
                else{
                	foreach($externall as $systemCatid => $v){
                		foreach($catRelation->subCategory as $sub){
                			$this->updateCategoryRelation($sub, $systemCatid, $parent);
                		}
            		} 
                }
            }
            else{
                echo $parent.' category not found to associate products';
                Mage::log('category not found: '.$parent,null,'catalogimport.log');
            }
        }
    }
  
     protected function duplicateCategory($categoryId, $parentId, $status){   //duplicating categoryid  
     	$default_root_category = $this->_default_category_id;
        $parent_id = ($parentId)?$parentId:$default_root_category;          	 	
        $isActive = ($status == 'Y')?1:0;           
        $category = Mage::getModel('catalog/category')->setStoreId($this->_store_id)->load($categoryId); //load category to duplicate
        $duplicate_category = Mage::getModel('catalog/category')
           		   ->setStoreId($this->_store_id);           
        $parent_category = $this->_initCategory($parentId, $this->_store_id);        
        if (!$parent_category->getId()) {
            exit;
        }
        $duplicate_category->addData(array('path'=>implode('/', $parent_category->getPathIds())));
        $duplicate_category->setParentId($parent_category->getId());
        $duplicate_category ->setAttributeSetId($duplicate_category->getDefaultAttributeSetId());

        $duplicate_category->setData('name', $category->getName());
        $duplicate_category->setData('include_in_menu', 1);
        $duplicate_category->setData('meta_title', $category->getmetaTitle());
        $duplicate_category->setData('meta_keywords', $category->getmetaKeywords());
        $duplicate_category->setData('meta_description', $category->getmetaDescription());
        $duplicate_category->setData('description', $category->getdescription());
        $duplicate_category->setData('available_sort_by','position');
        $duplicate_category->setData('default_sort_by','position');
        $duplicate_category->setData('is_active',$isActive);
        $duplicate_category->setData('external_id',$category->getexternalId());
        $duplicate_category->setData('external_cat_image',$category->getexternalCatImage());
        try {
            $validate = $duplicate_category->validate();
            if ($validate !== true) {
                foreach ($validate as $code => $error) {
                    if ($error === true) {
                        Mage::throwException(Mage::helper('catalog')->__('Attribute "%s" is required.', $code));
                    }
                    else {
                        Mage::throwException($error);
                    }
                }
            }
            $duplicate_category->save();
            return $duplicate_category->getId();
        }
        catch (Exception $e){
            echo $e->getMessage();
        }  	
        return false;
    }

	public function getTreeCategories($category_id, $p_id, $isActive, $isChild){ //$parentId, $isChild
		$duplicatedCategoryId = $this->duplicateCategory($category_id, $p_id, $isActive);
		$mapObj =  Mage::getModel('customimport/customimport');		
		$sub_category = Mage::getModel('catalog/category')->setStoreId($this->_store_id)->load($duplicatedCategoryId);
		$parent_category = Mage::getModel('catalog/category')->setStoreId($this->_store_id)->load($p_id);
		$ext_subid = $sub_category->getExternalId();
		$parent_external_id = $parent_category->getExternalId();	
	  	$mapObj->updateCategoryMappingInfo($ext_subid,$duplicatedCategoryId,$parent_external_id,$p_id);   

	    $allCats = Mage::getModel('catalog/category')->getCollection()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('parent_id',array('eq' => $category_id));   
	    foreach($allCats as $category){
	        $subcats = $category->getChildren();
	        $isActive = 'N';
	        $status_cat = $category->getData('is_active');
	        if($status_cat == 1){
	        	$isActive = 'Y';
	        }	       
	        if($subcats != ''){
	            $this->getTreeCategories($category->getId(), $duplicatedCategoryId, $isActive, true);
	        }else{
	        	$duplicatedSubcategoryId = $this->duplicateCategory($category->getId(), $duplicatedCategoryId, $isActive); // duplicated category id is parent for current subcategory
		        if($duplicatedSubcategoryId){		        						
					$sub_category = Mage::getModel('catalog/category')->setStoreId($this->_store_id)->load($duplicatedSubcategoryId);
					$parent_category = Mage::getModel('catalog/category')->setStoreId($this->_store_id)->load($duplicatedCategoryId);
					$ext_subid = $sub_category->getExternalId();
					$parent_external_id = $parent_category->getExternalId();	
				  	$mapObj->updateCategoryMappingInfo($ext_subid, $duplicatedSubcategoryId, $parent_external_id, $duplicatedCategoryId);   
		        }else{
		        	echo 'got some error while duplicating';
		        }
	        }
	    }
	}
  
    public function updateCategoryRelation($subcat, $p_id, $parent_external_id){ 
        $ext_subid = (string)$subcat->id;
        $actualSubId = $this->checkExternalId($ext_subid);
        $mapObj =  Mage::getModel('customimport/customimport');
        
        if($actualSubId){
            if(count($actualSubId) == 1){
                reset($actualSubId); //to take 1st key of array
                $subcat_id = key($actualSubId);                                                   
                $category_id = $mapObj->isSubcategoryExists($ext_subid, $p_id); // external subcat id , parent magento id
                
                if($category_id){
                   $category = Mage::getModel('catalog/category')
                   ->setStoreId($this->_store_id)
                   ->load($subcat_id);

	                $isActive = ((string)$subcat->isActive == 'Y')?1:0;
	                $category->setData('is_active',$isActive);
	                $category->save();                            
                }
                else{
                	$category_id = $mapObj->isCategoryExists($ext_subid);
                	if($category_id){
		             $isActive = ((string)$subcat->isActive);		             		             
		             $status = $this->getTreeCategories($category_id,$p_id, $isActive,false);	                            		               		              		
                	}else{
                		// category is not under any other parent , move
                	    $category = Mage::getModel('catalog/category')
		                    ->setStoreId($this->_store_id)
		                    ->load($subcat_id);
		                $category->move($p_id, 0);		                
		                $isActive = ((string)$subcat->isActive == 'Y')?1:0;		                
		                $category->setData('is_active',$isActive);
		                $category->save();
		                $mapObj->updateParent($p_id,$subcat_id);		               
		                $mapObj->updateCategoryMappingInfo($ext_subid,$subcat_id, $parent_external_id, $p_id);   		                
                	}
                }
            }
            else{          	
            	$category_id = $mapObj->isSubcategoryExists($ext_subid, $p_id);
            	if($category_id){           		
            	//	echo 'subcat present in this cat '.$ext_subid;
                    $category = Mage::getModel('catalog/category')->setStoreId($this->_store_id)->load($category_id);
	                $isActive = ((string)$subcat->isActive == 'Y')?1:0;
	                $category->setData('is_active', $isActive);
	                $category->save();                  
            	} 
            	else if($category_id = $mapObj->isCategoryExists($ext_subid)){ 
            		$isActive = ((string)$subcat->isActive);
            		$status = $this->getTreeCategories($category_id, $p_id, $isActive, false);
            	}else{
            		// category is not under any other parent , move           		
                	echo 'block will never execute';	
            	}	
            }                
        }
        else{
            if(count($externall) == 0){  
                Mage::log('subcategory id not found:'.$ext_subid, null, 'catalogimport.log');
            }
        }
    }

    public function importItem( &$item){
        $missingInfoRow = $this->_current_row -1;
        if(!isset($item->id) || trim($item->id)==''){
            $this->createLog('sku not found for product row # '.$missingInfoRow, "error");
          //  Mage::log('sku not found for product record #:'.$this->_current_row, null, 'catalogimport.log');
            return false;
        }
        if(!isset($item->attributeSetId) || trim($item->attributeSetId)==''){
            $this->createLog('AttributesetId not found for product Id # '.$item->id, "error");
         //   Mage::log('AttributesetId not found for product record #:'.$this->_current_row, null, 'catalogimport.log');
            return false;
        }
        if(!isset($item->type) || trim($item->type)==''){
            $this->createLog('Product type not found for product record # '.$item->id, "error");
         //   Mage::log('Product type not found for product record #:'.$this->_current_row, null, 'catalogimport.log');
            return false;
        }
        $itemids = $this->getItemIds($item);
        $pid = $itemids["pid"];
        $asid = $itemids["asid"];

        if(!isset($pid))
        {
            if(!isset($asid)){
                $this->createLog('cannot create product sku:'.(string)$item->id.', mentioned attributeset id is not available.product row id: #'.$missingInfoRow,'error');
              //  Mage::log('cannot create product sku:'.(string)$item->id,null,'catalogimport.log');
                return false;
            }
            if((string)$item->type == 'configurable'){
                $this->createConfigurableProduct($item, $asid); //create con product
            }
            else if((string)$item->type == 'simple'){
                $this->createProduct($item, $asid); //create simple product
            }
            else{
                 $this->createLog("Import function does not support product type of record: {$item->id}");
            }
            $this->_curitemids["pid"] = $pid;
            $isnew = true;
        }
        else{
            if((string)$item->type == 'configurable'){
                $this->updateConfigurableProduct($item, $pid); //create con product
            }
            else if((string)$item->type == 'simple'){
                $this->updateProduct($item, $pid); //create simple product
            }
        }
    }

    public function updateConfigurableProduct(&$item, $pid){
        $p_status = ((string)$item->isActive == 'Y')?1:2;
        $p_taxclass = ((string)$item->isTaxable == 'Y')?2:0;
        $SKU = (string)$item->id;
        $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $SKU);

        if ($product){
            $product->setData('name', (string)$item->name);
            $product->setPrice((real)$item->price);
            $product->setWeight((real)$item->weight);
            $product->setStatus($p_status);
            $product->setTaxClassId($p_taxclass);

            $product->setDescription((string)$item->longDescription);
            $product->setShortDescription((string)$item->shortDescription);
            $product->setMetaTitle((string)$item->pageTitle);
            $product->setMetaKeyword((string)$item->metaKeywords);
            $product->setMetaDescription((string)$item->metaDescription);
            $product->setExternalImage((string)$item->originalImageUrl);
            $product->setExternalSmallImage((string)$item->largeImageUrl);
            $product->setExternalThumbnail((string)$item->smallImageUrl);

            $attributeValues = $item->attributeValues;
            $attributeOcuurance = array(); //stores no. of occurance for all attributes
            $configAttributeValue = array(); // will use to take value of attributes that ocuures once
            $i =1;
            foreach($attributeValues->attribute as $attr){
                if(array_key_exists((string)$attr->id , $attributeOcuurance)){
                    $attributeOcuurance[(string)$attr->id] = (int)$attributeOcuurance[(string)$attr->id] + 1;
                }
                else{
                    $attributeOcuurance[(string)$attr->id] = $i;
                    $configAttributeValue[(string)$attr->id] = (string)$attr->valueDefId;
                }
            }
            $config_attribute_array = array();   //attributes with single occurance
            foreach($attributeOcuurance as $key=>$val){
                if($val == 1){
                    $config_attribute_array[] = $key;
                }
            }

            foreach($config_attribute_array as $attr){
                $external_id = $configAttributeValue[$attr];  // valueDefId from XML for an attribute
                $model = Mage::getModel('catalog/resource_eav_attribute');
                $loadedattr = $model->loadByCode('catalog_product', $attr);
                $attr_id = $loadedattr->getAttributeId();  // attribute id of magento
                $attr_type = $loadedattr->getFrontendInput();
                if($attr_type == 'select'){
                    $mapObj =  Mage::getModel('customimport/customimport');
                    $option_id = $mapObj->isOptionExistsInAttribute($external_id, $attr_id);
                    //  $product->setData($attr, (string)$item->name);
                    if($option_id){
                        $product->setData($attr, $option_id);
                    }
                }else{ //if attribute is textfield direct insert value
                    $product->setData($attr, $external_id);
                }

            }
            try{
                $product->save();
                $stockItem = Mage::getModel('cataloginventory/stock_item');
                $stockItem->assignProduct($product);
                $stockItem->setData('stock_id', (int)1);

                $stockItem->setData('use_config_manage_stock', (int)1);
                $stockItem->setData('min_qty', (int)0);
                $stockItem->setData('is_decimal_divided', (int)0);

                $stockItem->setData('qty', (int)0);
                $stockItem->setData('use_config_min_qty', 1);
                $stockItem->setData('use_config_backorders', 1);
                $stockItem->setData('min_sale_qty', 1);
                $stockItem->setData('use_config_min_sale_qty', 1);
                $stockItem->setData('use_config_max_sale_qty', 1);
                $stockItem->setData('is_in_stock', 1);
                $stockItem->setData('use_config_notify_stock_qty', 1);
                $stockItem->setData('manage_stock', 0);
                $stockItem->save();
                $stockStatus = Mage::getModel('cataloginventory/stock_status');
                $stockStatus->assignProduct($product);
                $stockStatus->saveProductStatus($product->getId(), 1);
               // echo "updated\n";
            }
            catch (Exception $e){
                echo " not added\n";
                echo "exception:$e";
            }
            $this->_updated_num++;
            unset($product);
            return $productId;
        }else{
            return false;
        }
    }

    public function createConfigurableProduct(&$item, $asid){
        $p_status = ((string)$item->isActive == 'Y')?1:2;
        $p_taxclass = ((string)$item->isTaxable == 'Y')?2:0;

        $product = new Mage_Catalog_Model_Product();
        $product->setTypeId('configurable');
        $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);

        $product->setSku((string)$item->id); //Product custom id
        $product->setWebsiteIds(array(Mage::app()->getStore(true)->getWebsite()->getId()));
        $product->setStoreIDs(array($this->_store_id));    // Default store id .

        $product->setAttributeSetId($asid);
        $product->setData('name', (string)$item->name);
        $product->setPrice((real)$item->price);
        $product->setWeight((real)$item->weight);
        $product->setStatus($p_status);
        $product->setTaxClassId($p_taxclass);

        $product->setDescription((string)$item->longDescription);
        $product->setShortDescription((string)$item->shortDescription);
        $product->setMetaTitle((string)$item->pageTitle);
        $product->setMetaKeyword((string)$item->metaKeywords);
        $product->setMetaDescription((string)$item->metaDescription);
        $product->setExternalImage((string)$item->originalImageUrl);
        $product->setExternalSmallImage((string)$item->largeImageUrl);
        $product->setExternalThumbnail((string)$item->smallImageUrl);

        $attributeValues = $item->attributeValues;
        $attributeOcuurance = array(); //stores no. of occurance for all attributes
        $configAttributeValue = array(); // will use to take value of attributes that ocuures once
        $i =1;

        foreach($attributeValues->attribute as $attr){
            if(array_key_exists((string)$attr->id, $attributeOcuurance)){
                $attributeOcuurance[(string)$attr->id] = (int)$attributeOcuurance[(string)$attr->id] + 1;
            }
            else{
                $attributeOcuurance[(string)$attr->id] = $i;
                $configAttributeValue[(string)$attr->id] = (string)$attr->valueDefId;
            }
        }
        $superattribute_array = array();    // attributes with multiple occurances
        $config_attribute_array = array();   //attributes with single occurance
        foreach($attributeOcuurance as $key => $val){
            if($val > 1){
                $superattribute_array[] = $key;
            }
            else{
                $config_attribute_array[] = $key;
            }
        }
        $attributes_array = array();
        if(count($superattribute_array) > 0){
            $super_attribute_created = $this->makeAttributeConfigurable($superattribute_array);
            if($super_attribute_created){
                foreach($superattribute_array as $attr){
                    $attributes_array[] = $attr;  // contains attribute codes
                }
                $ProductAttributeIds = array(); // array stores only super attribute id's
                $attribute_detail = array();       // stores super attribute's detail
                $attrnum = 0;
                foreach($attributes_array as $attribute_code){
                    $model = Mage::getModel('catalog/resource_eav_attribute');
                    $attr = $model->loadByCode('catalog_product', $attribute_code);
                    $attr_id = $attr->getAttributeId();
                    $ProductAttributeIds[] = $attr_id;
                    $attribute_label =   $attr->getFrontendLabel();
                    $attr_detail = array('id'=>NULL, 'label' => "$attribute_label", 'position' => NULL, 'attribute_id' => $attr_id, 'attribute_code' => "$attribute_code", 'frontend_label' => "$attribute_label",
                        'html_id' => "config_super_product__attribute_$attrnum");
                    $attribute_detail[] = $attr_detail;
                    $attrnum++;
                }
                $product->getTypeInstance()->setUsedProductAttributeIds($ProductAttributeIds);
                $product->setConfigurableAttributesData($attribute_detail);
                $product->setCanSaveConfigurableAttributes(1);

                foreach($config_attribute_array as $attr){
                    $external_id = $configAttributeValue[$attr];  // valueDefId from XML for an attribute
                    $model = Mage::getModel('catalog/resource_eav_attribute');
                    $loadedattr = $model->loadByCode('catalog_product', $attr);
                    $attr_id = $loadedattr->getAttributeId();  // attribute id of magento
                    $attr_type = $loadedattr->getFrontendInput();
                    if($attr_type == 'select'){
                        $mapObj =  Mage::getModel('customimport/customimport');
                        $option_id = $mapObj->isOptionExistsInAttribute($external_id, $attr_id);
                        if($option_id){
                            $product->setData($attr, $option_id);
                        }
                    }else{ //if attribute is textfield direct insert value
                        $product->setData($attr, $external_id);
                    }

                }
                try{
                    $product->save();
                    $stockItem = Mage::getModel('cataloginventory/stock_item');
                    $stockItem->assignProduct($product);
                    $stockItem->setData('stock_id', (int)1);

                    $stockItem->setData('use_config_manage_stock', (int)1);
                    $stockItem->setData('min_qty', (int)0);
                    $stockItem->setData('is_decimal_divided', (int)0);

                    $stockItem->setData('qty', (int)0);
                    $stockItem->setData('use_config_min_qty', 1);
                    $stockItem->setData('use_config_backorders', 1);
                    $stockItem->setData('min_sale_qty', 1);
                    $stockItem->setData('use_config_min_sale_qty', 1);
                    $stockItem->setData('use_config_max_sale_qty', 1);
                    $stockItem->setData('is_in_stock', 1);
                    $stockItem->setData('use_config_notify_stock_qty', 1);
                    $stockItem->setData('manage_stock', 0);
                    $stockItem->save();
                    $stockStatus = Mage::getModel('cataloginventory/stock_status');
                    $stockStatus->assignProduct($product);
                    $stockStatus->saveProductStatus($product->getId(), 1);
                }
                catch (Exception $e){
                    echo "exception:$e";
                }
            }else{
                echo 'Could not get super attribute for product. Hence skipped product' .(string)$item->id ;
            }
        }else{
            echo 'Super attribute is missing. Hence skipped product' .(string)$item->id ;
        }
    }
    
    public function createProduct(&$item, $asid){
        $p_status = ((string)$item->isActive == 'Y')?1:2;
        $p_taxclass = ((string)$item->isTaxable == 'Y')?2:0;

        $product = new Mage_Catalog_Model_Product();
        $product->setTypeId('simple');
        $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);

        $product->setSku((string)$item->id); //Product custom id
        $product->setWebsiteIds(array(Mage::app()->getStore(true)->getWebsite()->getId()));  //Default website (main website) ?? To Do : make it dynamic
        $product->setStoreIDs(array($this->_store_id));    // Default store id .
        $product->setStockData(array(      // Getting no info for quantity, hence statically using 100 for now
            'is_in_stock' => 1,
            'qty' => 100,
            'manage_stock' => 1));
        $product->setAttributeSetId($asid);
        $product->setData('name', (string)$item->name);
        $product->setPrice((real)$item->price);
        $product->setWeight((real)$item->weight);
        $product->setStatus($p_status);
        $product->setTaxClassId($p_taxclass);

        $product->setDescription((string)$item->longDescription);
        $product->setShortDescription((string)$item->shortDescription);
        $product->setMetaTitle((string)$item->pageTitle);
        $product->setMetaKeyword((string)$item->metaKeywords);
        $product->setMetaDescription((string)$item->metaDescription);
        $product->setExternalImage((string)$item->originalImageUrl);
        $product->setExternalSmallImage((string)$item->largeImageUrl);
        $product->setExternalThumbnail((string)$item->smallImageUrl);

        $attributeValues = $item->attributeValues;
        $attributeOcuurance = array(); //stores no. of occurance for all attributes
        $configAttributeValue = array(); // will use to take value of attributes that ocuures once
        $multiple_values = array();      // stores an array of available values
        $i =1;
        foreach($attributeValues->attribute as $attr){
            if(array_key_exists((string)$attr->id ,$attributeOcuurance)){
                $multiple_values[(string)$attr->id][] = (string)$attr->valueDefId;
                $attributeOcuurance[(string)$attr->id] = (int)$attributeOcuurance[(string)$attr->id] + 1;
            }
            else{
                $multiple_values[(string)$attr->id][] = (string)$attr->valueDefId;
                $attributeOcuurance[(string)$attr->id] = $i;
            }
        }
        $skipStatus = 0;
        foreach($multiple_values as $attribute_code=>$attribute_values){
            $model = Mage::getModel('catalog/resource_eav_attribute');
            $loadedattr = $model->loadByCode('catalog_product', $attribute_code);
            $attr_id = $loadedattr->getAttributeId();  // attribute id of magento
            if(!$attr_id){
              //  echo 'attribute '.$attribute_code . 'is not available in magento database.';
                  $this->createLog("Skipped product {$item->id}, attribute is not available in magento database.: {$attribute_code}");
                  $skipStatus = 1;
                  break;
            }
            else{
                $attr_type = $loadedattr->getFrontendInput();
                if($attr_type =='select' && count($attribute_values) == 1){
                    $mapObj =  Mage::getModel('customimport/customimport');
                    $option_id = $mapObj->isOptionExistsInAttribute($attribute_values[0], $attr_id);
                    if($option_id){
                        $product->setData($attribute_code, $option_id);
                    }
                }
                if($attr_type =='select' && count($attribute_values)>1){
                    //multiple values for attribute which is not multiselect
                  //  echo 'Attribute '. $attribute_code. 'can not have multiple values. Hence skipping product having id' . (string)$item->id;
                        $skipStatus = 1;
                        break;
                 }
                if($attr_type =='multiselect'){
                    $multivalues = array();
                    foreach($attribute_values as $value){
                        $mapObj =  Mage::getModel('customimport/customimport');
                        $option_id = $mapObj->isOptionExistsInAttribute($value, $attr_id);
                        if($option_id){
                            $multivalues[] = $option_id;
                        }
                    }
                    $product->addData( array($attribute_code => $multivalues) );
                }
                if($attr_type =='text' || $attr_type =='textarea'){ // if type is text/textarea
                    $product->setData($attribute_code, $attribute_values[0]);
                }
            }
        }
        try{
            if($skipStatus == 0){
	           $productId =  $product->save()->getId();
	            if ($productId) {
	                $this->_created_num++;
	                unset($product);
	                unset($multiple_values);
	                unset($attributeOcuurance);
	                return $productId;
	            }
	            else{
	            	echo 'Skipped product due to improper attribute values :'.(string)$item->id;
	            	// Mage::log('Skipped product due to improper attribute values :'.(string)$item->id,null,'catalogimport.log');           	
	            }	           
            }else{
            	echo 'Skipped product due to some error while save :'.(string)$item->id;
            //	Mage::log('Skipped product due to some error while save :'.(string)$item->id,null,'catalogimport.log');  
            }                                
        }
        catch(Mage_Eav_Model_Entity_Attribute_Exception $e){
            echo $e->getAttributeCode();
            echo $e->getMessage();
        }
    }

    public function updateProduct(&$item, $pid){
        $p_status = ((string)$item->isActive == 'Y')?1:2;
        $p_taxclass = ((string)$item->isTaxable == 'Y')?2:0;
        $SKU = (string)$item->id;
        $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $SKU);

        if ($product){
            //Product found, so we need to update it in Magento.
            $product->setData('name', (string)$item->name);
            $product->setPrice((real)$item->price);
            $product->setWeight((real)$item->weight);
            $product->setStatus($p_status);
            $product->setTaxClassId($p_taxclass);

            $product->setDescription((string)$item->longDescription);
            $product->setShortDescription((string)$item->shortDescription);
            $product->setMetaTitle((string)$item->pageTitle);
            $product->setMetaKeyword((string)$item->metaKeywords);
            $product->setMetaDescription((string)$item->metaDescription);
            $product->setExternalImage((string)$item->originalImageUrl);
            $product->setExternalSmallImage((string)$item->largeImageUrl);
            $product->setExternalThumbnail((string)$item->smallImageUrl);

            $attributeValues = $item->attributeValues;
            $attributeOcuurance = array(); //stores no. of occurance for all attributes
            $configAttributeValue = array(); // will use to take value of attributes that ocuures once
            $multiple_values = array();
            $i =1;
            foreach($attributeValues->attribute as $attr){
                if(array_key_exists((string)$attr->id ,$attributeOcuurance)){
                    $multiple_values[(string)$attr->id][] = (string)$attr->valueDefId;
                    $attributeOcuurance[(string)$attr->id] = (int)$attributeOcuurance[(string)$attr->id] + 1;
                }
                else{
                    $multiple_values[(string)$attr->id][] = (string)$attr->valueDefId;
                    $attributeOcuurance[(string)$attr->id] = $i;
                    //  $configAttributeValue[(string)$attr->id] = (string)$attr->valueDefId;
                }
            }
            $skipStatus = 0;
            foreach($multiple_values as $attribute_code => $attribute_values){
                $model = Mage::getModel('catalog/resource_eav_attribute');
                $loadedattr = $model->loadByCode('catalog_product', $attribute_code);
                $attr_id = $loadedattr->getAttributeId();  // attribute id of magento
                if(!$attr_id){
                    echo 'attribute '.$attribute_code . 'is not available in magento database.Hence skipping product having id' . (string)$item->id;
                }
                else{
                    $attr_type = $loadedattr->getFrontendInput();
                    if($attr_type =='select' && count($attribute_values) == 1){
                        $mapObj =  Mage::getModel('customimport/customimport');
                        $option_id = $mapObj->isOptionExistsInAttribute($attribute_values[0], $attr_id);
                        if($option_id){
                            $product->setData($attribute_code, $option_id);
                        }
                    }
                    if($attr_type =='select' && count($attribute_values)>1){
                        //multiple values for attribute which is not multiselect
                        echo 'Attribute '. $attribute_code. 'can not have multiple values. Hence skipping product having id' . (string)$item->id;
                        $skipStatus = 1;
                        break;
                    }
                    if($attr_type =='multiselect'){
                        $multivalues = array();
                        foreach($attribute_values as $value){
                            $mapObj =  Mage::getModel('customimport/customimport');
                            $option_id = $mapObj->isOptionExistsInAttribute($value, $attr_id);
                            if($option_id){
                                $multivalues[] = $option_id;
                            }
                        }
                        $product->addData( array($attribute_code => $multivalues) );
                    }
                    if($attr_type =='text' || $attr_type =='textarea'){ // if type is text/textarea
                        $product->setData($attribute_code, $attribute_values[0]);
                    }
                }
            }
            if($skipStatus == 0){
	            $productId = $product->save()->getId();
	            $this->_updated_num++;	
	          //  $productId = $product->getId();
	            $stockItem =Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
	            $stockItemId = $stockItem->getId();
	            $stockItem->setData('manage_stock', 1);
	            $stockItem->setData('qty', 100);
	            $stockItem->save();
	            unset($product);
	            return $productId;
            }else{
            	// echo 'Skipped product due to improper attribute values :'.(string)$item->id;
            	// Mage::log('Skipped product due to improper attribute values :'.(string)$item->id,null,'catalogimport.log');  
            }
            
        }else{
        	  	echo 'Skipped product due to some error while save :'.(string)$item->id;
            //	Mage::log('Skipped product due to some error while save :'.(string)$item->id,null,'catalogimport.log'); 
        }
    }

    public function getAttributeSetId($attrSetName){
        $attributeSetId  = Mage::getModel('eav/entity_attribute_set')
            ->getCollection()
            ->setEntityTypeFilter(4) // 4is entity type id...4= product
            ->addFieldToFilter('attribute_set_name', $attrSetName)
            ->getFirstItem()
            ->getAttributeSetId();

        return $attributeSetId;
    }

    public function getItemIds($item){
        $external_set_id =  (string)$item->attributeSetId;
        $mapObj =  Mage::getModel('customimport/customimport');
        $magento_set_id = $mapObj->getAttributeSetIdByExternalId($external_set_id);

        $sku=(string)$item->id;
        if($sku!= $this->_curitemids["sku"]){
            //try to find item ids in db
            $cids = $this->getProductIds($sku);
            if($cids!==false){
                //if found use it
                $this->_curitemids=$cids;
            }else{
                //only sku & attribute set id from datasource otherwise.
                //  $this->_curitemids=array("pid"=>null,"sku"=>$sku,"asid"=>isset($magento_set_id)?$magento_set_id:$this->getAttributeSetId('Default'));
                $this->_curitemids=array("pid"=>null,"sku"=>$sku,"asid"=>isset($magento_set_id)?$magento_set_id:null);
            }
            //do not reset values for existing if non admin
            $this->onNewSku($sku,($cids!==false));
            unset($cids);
        }else{
            $this->onSameSku($sku);
        }
        return $this->_curitemids;
    }


    public function getProductIds($sku){
        $toReturn = array();
        $product1 = Mage::getModel('catalog/product');
        $productCollection = $product1->getCollection()
            ->addAttributeToSelect('sku','entity_id','attribute_set_id')
            ->addAttributeToFilter('sku', $sku)
            ->load();
        if(count($productCollection)>0){
            foreach($productCollection as $product){
                $productArr = $product->getData();
                $toReturn['pid'] =  $productArr['entity_id'];
                $toReturn['asid'] = $productArr['attribute_set_id'];
                return $toReturn;
            }
        }else{
            return false;
        }
    }

    public function clearOptCache(){
        unset($this->_optidcache);
        $this->_optidcache=array();
    }

    public function onNewSku($sku,$existing){
        $this->clearOptCache();
        //only assign values to store 0 by default in create mode for new sku
        //for store related options
        if(!$existing)
        {
            $this->_dstore=array(0);
        }
        else
        {
            $this->_dstore=array();
        }
        $this->_same=false;
    }

    public function onSameSku($sku){
        unset($this->_dstore);
        $this->_dstore=array();
        $this->_same=true;
    }

    public function createLog($msg){
        $this->_log_array[] = $msg;
    }


    public function lookup($listType){
        $t0=microtime(true);
        //	echo "Performing Datasouce Lookup...startup";
        $count = count($listType);
        $this->_item_num = $count;
        $t1 = microtime(true);
        $time = $t1-$t0;  
         $this->createLog("Found $count records","startup");
      //  echo '<br /> memory:'. $mem;
    }
    
    /*
     * create category
     * */
    protected function createCategory($item){   	
    	$default_root_category = $this->_default_category_id;
        $parent_id = ((string)$item->isRoot == 'Y')?1:$default_root_category;
        $isActive = ((string)$item->isActive == 'Y')?1:0;

        $category = Mage::getModel('catalog/category')
            ->setStoreId($this->_store_id);
        $parent_category = $this->_initCategory($parent_id, $this->_store_id);
        if (!$parent_category->getId()) {
            exit;
        }
        $category->addData(array('path'=>implode('/',$parent_category->getPathIds())));
        /* @var $validator Mage_Catalog_Model_Api2_Product_Validator_Product */
        $category->setParentId($parent_category->getId());
        $category ->setAttributeSetId($category->getDefaultAttributeSetId());
        $category->setData('name',(string)$item->name);
        $category->setData('include_in_menu',1);
        $category->setData('meta_title',(string)$item->pageTitle);
        $category->setData('meta_keywords',(string)$item->metaKeywords);
        $category->setData('meta_description',(string)$item->metaDescription);
        $category->setData('description',(string)$item->description);


        $category->setData('available_sort_by','position');
        $category->setData('default_sort_by','position');
        $category->setData('is_active',$isActive);
        $category->setData('external_id',(string)$item->id);
        $category->setData('external_cat_image',(string)$item->imageUrl);
        try {
            $validate = $category->validate();
            if ($validate !== true) {
                foreach ($validate as $code => $error) {
                    if ($error === true) {
                        Mage::throwException(Mage::helper('catalog')->__('Attribute "%s" is required.', $code));
                    }
                    else {
                        Mage::throwException($error);
                    }
                }
            }
            $category->save();
        }
        catch (Exception $e){
            echo $e->getMessage();
        }
    }

    protected function updateCategory($item,$categoryId){
        $category = Mage::getModel('catalog/category')->load($categoryId);
        $isActive = ((string)$item->isActive == 'Y')?1:0;
        $category->setData('name',(string)$item->name);
        $category->setData('include_in_menu',1);
        $category->setData('meta_title',(string)$item->pageTitle);
        $category->setData('meta_keywords',(string)$item->metaKeywords);
        $category->setData('meta_description',(string)$item->metaDescription);
        $category->setData('description',(string)$item->description);
        $category->setData('is_active',$isActive);
        $category->setData('external_cat_image',(string)$item->imageUrl);
        $category->save();
    }

    /**
     * main function to import category
     */
    protected function importCategory($item){
        $externalId = (string)$item->id;
        $externall = $this->checkExternalId($externalId);

        if($externall){
            if(count($externall) == 1){
                //update already existing category with this id
                reset($externall); //to take 1st key of array
                $first_key = key($externall);
                $this->updateCategory($item,$first_key);
                $this->_updated_num++;
            }
            else{
            	foreach($externall as $systemCatid=>$v){
            		$this->updateCategory($item,$systemCatid);
            	} 
            }
        }else{
            if(count($externall) == 0){  // category is not available hence create new
                $this->createCategory($item);
                $this->_created_num++;
            }
        }
    }

    /**
     * checks for a category existance using external id
     * @param external id of category
     */

    protected function checkExternalId($externalId){
        $catsWithCustomAttr = array();
        $collection = Mage::getModel('catalog/category')->getCollection();
        $collection->addAttributeToSelect("external_id");
        //Do a left join, and get values that aren't null - you could add other conditions in the second parameter also (check out all options in the _getConditionSql method of lib/Varien/Data/Collection/Db.php
        $collection->addAttributeToFilter('external_id', $externalId,  'left');
        foreach($collection as $category){
            $catsWithCustomAttr[$category->getId()] = $category->getExternalId();  //Or you could say $category->getData('your_custom_category_attribute')
        }

        return $catsWithCustomAttr;
    }

    /**
     * loads a category, if exists
     * @param category id
     */

    protected function _initCategory($categoryId, $store = null){
        try{
            $category = Mage::getModel('catalog/category')
                ->setStoreId($store)
                ->load($categoryId);
            if (!$category->getId()) {
                Mage::throwException(Mage::helper('catalog')->__('Parent category "%s" is not available.', $categoryId));
            }
        }
        catch (Exception $e){
            echo $e->getMessage();
        }
        return $category;
    }


    public function getCurrentRow(){
        return $this->_current_row;
    }

    public function setCurrentRow($cnum){
        $this->_current_row=$cnum;
    }

    /**
     * parses <categoryProducts> block , and returns <categoryProducts><categoryProduct> block
     */
    public function associatedProductsCategory(){
        $xmlObj =  $this->_xmlObj;
        $xmlData = $xmlObj->getNode();

        if( $xmlData->categoryProducts->categoryProduct instanceof Varien_Simplexml_Element){
            return $xmlData->categoryProducts->categoryProduct;
        }else{
            return 'no data ';
        }
    }

    /*
     * associates a product with a category
     * */

    public function associateProductToCategory( $productDetail,$catId){   //actual id of category
        $cat_api = new Mage_Catalog_Model_Category_Api;
        $newProduct = Mage::getModel('catalog/product')->loadByAttribute('sku',(string)$productDetail->id);
        if($newProduct){
            $category = Mage::getModel('catalog/category')
                ->setStoreId($this->_store_id)
                ->load($catId);
            $positions = $category->getProductsPosition();
            $productDetail->position;
            if( (string)$productDetail->isActive == 'Y'){
                // assignproduct is default function of magento, to assign product in a category
                $cat_api->assignProduct($catId, $newProduct->getId(),(real)$productDetail->position);
            }else{
                if(isset($positions[$newProduct->getId()])){
                    // removeProduct is default function of magento, to remove product from a category
                    $cat_api->removeProduct($catId, $newProduct->getId());
                }else{
                    //echo 'does not';
                }
            }
        }else{
            echo (string)$productDetail->id.' product does not exists';
            Mage::log('product does not exists :'.(string)$productDetail->id,null,'catalogimport.log');
        }
    }

    public function associateProducts($association){
        foreach($association as $associate){
            $parent = (string)$associate->categoryId;
            $externall = $this->checkExternalId($parent);
            if($externall){
                if(count($externall) == 1){
                    reset($externall);
                    $first_key = key($externall);
                    foreach($associate->product as $product){
                        $this->associateProductToCategory($product,$first_key);
                    }
                }
                else{
                	foreach($externall as $systemCatid=>$v){
                		foreach($associate->product as $product){
                			$this->associateProductToCategory($product,$systemCatid);
                		}
                	}               	               	
                }
            }
            else{
                if(count($externall) == 0){
                    $this->createLog('category id not found: # '.$parent, "error");
                    Mage::log('category id not found: '.$parent,null,'catalogimport.log');
                }
            }
        }
    }
    public function associatePdtPdt($association){
        foreach($association as $associate){
            $mainProduct = Mage::getModel('catalog/product')->loadByAttribute('sku',(string)$associate->productIdFrom);
            if ($mainProduct) {
                $productId = $mainProduct->getId();
                $relatedArray=array();
                $upsellArray=array();
                $crossArray=array();
                $associatedArray = array();
                foreach($associate->associatedProduct as $association){
                    if( $association instanceof Varien_Simplexml_Element){  // if associatedProduct is an object in form of <associatedProduct>
                        unset($prid);
                        $prid = Mage::getModel('catalog/product')->getIdBySku((string)$association->id); // get id of associated product
                        if($prid && (string)$association->isActive == 'Y') {
                            $position = (string)$association->position ? (string)$association->position : 0;
                            if((string)$association->assocType == 0){
                                $crossArray[$prid]=array('position'=>$position);
                            }
                            else if((string)$association->assocType == 1){
                                $upsellArray[$prid]=array('position'=>$position);
                            }
                            else if((string)$association->assocType == 2){
                                $relatedArray[$prid]=array('position'=>$position);
                            }else if((string)$association->assocType == 3){
                                $associatedArray[] = $prid;
                            }
                        }
                    }
                }
                $mainProduct->setCrossSellLinkData($crossArray);
                $mainProduct->setUpSellLinkData($upsellArray);
                $mainProduct->setRelatedLinkData($relatedArray);
                $mainProduct->save();
                
                if(count($associatedArray) > 0){
                Mage::getResourceModel('catalog/product_type_configurable')
                    ->saveProducts( $mainProduct, $associatedArray );
                }                   
                unset($crossArray);
                unset($upsellArray);
                unset($relatedArray);
                unset($associatedArray);
            }else{
                $this->createLog('product not found for association: # '.$associate->productIdFrom, "error");
                Mage::log('product not found for association: # '.$associate->productIdFrom,null,'catalogimport.log');
            }
        }
    }

    public function associatedProductsProducts(){
        $xmlObj =  $this->_xmlObj;
        $xmlData = $xmlObj->getNode();
        if( $xmlData->productAssociations->association instanceof Varien_Simplexml_Element){
            return $xmlData->productAssociations->association;
        }else{
            Mage::log('Associatation block is empty',null,'catalogimport.log');
            $this->createLog('Associatation block is empty: # ');
        }
    }

    public function makeAttributeConfigurable($superattribute_array){
        $result = false;
        $model = Mage::getModel('catalog/resource_eav_attribute');

        foreach($superattribute_array as $attribute_id){
            $attr = $model->loadByCode('catalog_product',$attribute_id);
            $attr_id = $attr->getAttributeId();
            if($attr_id){
                if($attr_configurable = $attr->getIsConfigurable()){
                    $result =  true;
                }else{
                    $attribute_type =  $attr->getFrontendInput();
                    $_attribute_data = array();
                    if($attribute_type == 'select'){
                        $_attribute_data['is_configurable'] = 1;
                        $model->addData($_attribute_data);
                        $attr->save();
                        $result =  true;
                    }else{
                        echo 'attribute '.$attribute_id. ' can not be updated to become superattribute';
                        
                        $result =  false;
                    }
                }
            }else{
                echo 'attribute '.$attribute_id.' does not exists';
                $result =  false;
            }
        }

        return $result;
    }

    public function createAttribute($attribute){
        $mapobj = Mage::getModel('customimport/customimport');
        $attr_values = (array)$attribute->values;

        $attribute_type = 'select';
        if(isset($attribute->type)){
            $att_type = (string)$attribute->type;
            $magento_array_values = array('text','textarea','date','boolean','multiselect','select','price','media_image','weee');
            if (in_array($att_type, $magento_array_values)) {
                $attribute_type = $att_type;
            }
        }
        $count_value =  count($attr_values['valueDef']);
        $_attribute_data = array(
            'attribute_code' => (string)$attribute->id,
            'is_global' => '1',
            'frontend_input' => $attribute_type, //'text/select',
            'default_value_text' => '',
            'default_value_yesno' => '0',
            'default_value_date' => '',
            'default_value_textarea' => '',
            'is_unique' => '0',
            'is_required' => '0',
            'apply_to' => array('simple','configurable'), //array('grouped')
            'is_configurable' => '0',
            'is_searchable' => '0',
            'is_visible_in_advanced_search' => '0',
            'is_comparable' => '0',
            'is_used_for_price_rules' => '0',
            'is_wysiwyg_enabled' => '0',
            'is_html_allowed_on_front' => '1',
            'is_visible_on_front' => '1',
            'used_in_product_listing' => '0',
            'used_for_sort_by' => '0',
            'frontend_label' => array((string)$attribute->name)
        );

        $model = Mage::getModel('catalog/resource_eav_attribute');
        //  $attr =  Mage::getModel('eav/entity_attribute')->loadByCode('catalog_product', (string)$attribute->id);
        //Load the particular attribute by id
        $attr = $model->loadByCode('catalog_product',(string)$attribute->id);
        $attr_id = $attr->getAttributeId();

        if($attr_id !=''){
            $attr->addData($_attribute_data);
            $option['attribute_id'] = $attr_id;

            if($count_value > 0 && ($attribute_type == 'select' || $attribute_type == 'multiselect')){
                for($i =0; $i<$count_value ; $i++){
                    $attrdet = $attr_values['valueDef'][$i];
                    $optionId = $mapobj->isOptionExistsInAttribute($attrdet->id,$attr_id);
                    if(!isset($optionId)){
                        $option['value']['option_'.$i][0] = $attrdet->value;
                        $option['order']['option_'.$i] = $i;
                        $option['externalid']['option_'.$i] = $attrdet->id;
                    }
                    else{
                        $option['value'][$optionId][0] = $attrdet->value;
                        $option['order'][$optionId] = $i;
                        $option['externalid'][$optionId] = $attrdet->id;
                    }
                    $attr->setOption($option);
                }
            }
            $attr->save();
        }
        else{
            if (!isset($_attribute_data['is_configurable'])) {
                $_attribute_data['is_configurable'] = 0;
            }
            if (!isset($_attribute_data['is_filterable'])) {
                $_attribute_data['is_filterable'] = 0;
            }
            if (!isset($_attribute_data['is_filterable_in_search'])) {
                $_attribute_data['is_filterable_in_search'] = 0;
            }
            if (is_null($model->getIsUserDefined()) || $model->getIsUserDefined() != 0) {
                $_attribute_data['backend_type'] = $model->getBackendTypeByInput($_attribute_data['frontend_input']);
            }
            $defaultValueField = $model->getDefaultValueByInput($_attribute_data['frontend_input']);
            if ($defaultValueField) {
                $_attribute_data['default_value'] = $this->getRequest()->getParam($defaultValueField);
            }
            $model->addData($_attribute_data);
            $model->setEntityTypeId(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId());
            $model->setIsUserDefined(1);
            try {
                $model->save();
                unset($model);
                if($count_value > 0){
                    $model = Mage::getModel('catalog/resource_eav_attribute');
                    $attr = $model->loadByCode('catalog_product',(string)$attribute->id);
                    $attr_id = $attr->getAttributeId();
                    $option['attribute_id'] = $attr_id;

                    for($i =0; $i<$count_value ; $i++){
                        $attrdet = $attr_values['valueDef'][$i];
                        $optionId = $mapobj->isOptionExistsInAttribute($attrdet->id,$attr_id);
                        if(!isset($optionId)){
                            $option['value']['option_'.$i][0] = $attrdet->value;
                            $option['order']['option_'.$i] = $i;
                            $option['externalid']['option_'.$i] = $attrdet->id;
                        }
                        else{
                            $option['value'][$optionId][0] = $attrdet->value;
                            $option['order'][$optionId] = $i;
                            $option['externalid'][$optionId] = $attrdet->id;
                        }
                    }
                    $attr->setOption($option);
                    $attr->save();
                }
            } catch (Exception $e) {
                echo '<p>Sorry, error occured while trying to save the attribute. Error: '.$e->getMessage().'</p>';
            }
        }
    }

    public function importAttribute($parsedAttribute){
        foreach($parsedAttribute as $attribute){
            $this->createAttribute($attribute);
        }
        $this->reindexDB(7);	// 7 is used after attribute creation.
    }

    public function parseAttribute(){
        $xmlObj =  $this->_xmlObj;
        $xmlData = $xmlObj->getNode();
        if( $xmlData->attributeConfiguration->attribute instanceof Varien_Simplexml_Element){
            return $xmlData->attributeConfiguration->attribute;
        }
    }

    public function parseAttributeSet(){
        $xmlObj =  $this->_xmlObj;
        $xmlData = $xmlObj->getNode();
        if( $xmlData->attributeConfiguration->attributeSet instanceof Varien_Simplexml_Element){
            return $xmlData->attributeConfiguration->attributeSet;
        }
    }

    public function importAttributeSet($parsedAttributeSet){
        $attributeSet_groups = array();
        $attributeSet_groups['status'] = array();
        $attributeSet_groups['id'] = array();

        $attributegroup_id = array();
        $attributegroup_status = array();

        foreach($parsedAttributeSet as $set){
            $attributeSetId = (string)$set->id;
            $attributeSetName = (string)$set->name;

            if($attributeSetName == ''){
                $attributeSetName = $attributeSetId;
            }
            $attributeGrp = $set->attributeGroups->attributeGroup;
            $attribute_set_id = $this->createAttributeSet($attributeSetName,$attributeSetId);

            foreach($attributeGrp as $attrgroup){
                $attributegroup_id = (string)$attrgroup->id;
                $attributegroup_status = (string)$attrgroup->isActive;
                $attributeSet_groups['id'][$attributegroup_id][] = $attribute_set_id ;
                $attributeSet_groups['status'][$attributegroup_id][] = $attributegroup_status ;
            }
            unset($attributegroup_status);
            unset($attributegroup_id);
        }
        $this->attributeGroupsGlobal = $attributeSet_groups;
    }


    public function createAttributeSet($attribute_set_name, $external_id ) {
        $helper = Mage::helper('adminhtml');
        $mapobj = Mage::getModel('customimport/customimport');
        $attributeSetId = $mapobj->getAttributeSetIdByExternalId($external_id);

        $entityTypeId = $this->getEntityTypeId();
        $modelSet  = Mage::getModel('eav/entity_attribute_set')
            ->setEntityTypeId($entityTypeId);

        if(isset($attributeSetId) && !empty($attributeSetId)) {
            $modelSet->load($attributeSetId);
            if (!$modelSet->getId()) {
                Mage::throwException(Mage::helper('catalog')->__('This attribute set no longer exists.'));
            }
            //filter html tags
            $modelSet->setAttributeSetName(trim($attribute_set_name));
            $modelSet->validate();
            $modelSet->save();
            return $attributeSetId;
        }
        // to add attribute set
        $modelSet->setAttributeSetName($attribute_set_name);

        $defaultAttributeSetId = $this->getAttributeSetId('Default');
        try{
            if ($modelSet->validate()) {
                $attributeSetId = $modelSet->save()->getAttributeSetId();
                $modelSet->initFromSkeleton($defaultAttributeSetId)->save();
            }
        }catch(Exception $e){
            echo 'already exists';
        }
       // $attributeSetId = $this->getAttributeSetId($attribute_set_name);
        $mapobj->mapAttributeSet($external_id,$attributeSetId);
        return $attributeSetId;
    }

    public function parseAttributegrp(){
        $xmlObj =  $this->_xmlObj;
        $xmlData = $xmlObj->getNode();

        if( $xmlData->attributeConfiguration->attributeGroup instanceof Varien_Simplexml_Element){
            return $xmlData->attributeConfiguration->attributeGroup;
        }
    }

    public function importAttributeGrp($parsedAttribute){
        $setOfGroups = $this->attributeGroupsGlobal;
        foreach($parsedAttribute as $attribute){
            $attributeSets = array();

            $attributesOfGroup = array(); // array to store attribute detail info of attribute group
            $attributesIdOfGroup = array();
            $attributesStatusOfGroup = array();
            $attributesSequenceOfGroup = array();

            $groupAttributes = $attribute->groupedAttributes->attribute;
            foreach($groupAttributes as $grp){
                $attributesIdOfGroup[] = (string)$grp->id;
                $attributesStatusOfGroup[] = (string)$grp->isActive ? (string)$grp->isActive : 'Y';
                $attributesSequenceOfGroup[] = (string)$grp->position ? (string)$grp->position : 0;
            }
            $attributesOfGroup['attr_ids'] = $attributesIdOfGroup;
            $attributesOfGroup['attr_status'] = $attributesStatusOfGroup;
            $attributesOfGroup['attr_sequence'] = $attributesSequenceOfGroup;

            $groupId = (string)$attribute->id;
            $groupName = (string)$attribute->name;

            if($groupName ==''){
                $groupName = $groupId;
            }
            $attributeSets['set_ids'] = $setOfGroups['id'][$groupId];
            $attributeSets['set_status'] = $setOfGroups['status'][$groupId];

            $this->manageAttributeGroup($groupName,$groupId,$attributeSets,$attributesOfGroup);
            unset($attributeSets);
            unset($attributesOfGroup);
            unset($attributesIdOfGroup);
            unset($attributesStatusOfGroup);
            unset($attributesSequenceOfGroup);
        }
    }


    public function manageAttributeGroup($attribute_group_name,$attribute_group_id,$attribute_set_ids,$attributesOfGroup) {   // setname and group are arrays , $attribute_group_id is external group id
        foreach($attribute_set_ids['set_ids'] as $k=>$set_id){  // loop for attribute sets
            if($attribute_set_ids['set_status'][$k] == 'Y'){
                //create group here
                $res = $this->createAttributeGroup($attribute_group_name ,$attribute_group_id, $set_id);
                foreach($attributesOfGroup['attr_ids'] as $k=>$attribute_code){ // loop for all attributes
                    if($attributesOfGroup['attr_status'][$k] == 'Y'){
                        // insert attributes inside group
                        $attributeSortOrder = 0;
                        if (isset($attributesOfGroup['attr_sequence'])) {
                            $attributeSortOrder = $attributesOfGroup['attr_sequence'][$k];
                        }
                        $this->importAttributeInsideGroup($attribute_group_id, $set_id , $attribute_code,$attributeSortOrder);

                    }else{
                        $this->removeAttributeFromGroup($attribute_group_id, $set_id , $attribute_code);
                    }
                }
            }
            else{
                // remove group from this set
                $this->removeAttributeGroup($attribute_group_name ,$attribute_group_id, $set_id);

            }
        }
    }

    public function removeAttributeFromGroup($attribute_group_id, $attributeSetId , $attribute_code){
        $mapobj = Mage::getModel('customimport/customimport');
        $attributeGroupId = $mapobj->getAttributeGroupByExternalId($attribute_group_id, $attributeSetId);  	// $attribute_group_id is external group id

        if($attributeGroupId){
            $setup = new Mage_Eav_Model_Entity_Setup('core_setup');
            $attribute_id=$setup->getAttributeId('catalog_product', $attribute_code);

            $attribute_exists = $mapobj->isAttributeExistsInGroup($attribute_id,$attributeGroupId);
            if($attribute_exists){
                $installer = $this->getInstaller();
                $installer->startSetup();
                $installer->deleteTableRow('eav/entity_attribute', 'attribute_id', $attribute_id, 'attribute_set_id', $attributeSetId);

                $installer->endSetup();
            }else{
                // do nothing

            }
        }
    }

    public function removeAttributeGroup($attribute_group_name ,$attribute_group_id, $attributeSetId){
        $setup = new Mage_Eav_Model_Entity_Setup('core_setup');
        $mapobj = Mage::getModel('customimport/customimport');
        $attributeGroupId = $mapobj->getAttributeGroupByExternalId($attribute_group_id, $attributeSetId);
        if($attributeGroupId){
          //  echo '<br/>found group, delete this group';
            $setup->removeAttributeGroup('catalog_product',$attributeSetId,$attributeGroupId);
            //   $model->load($attributeGroupId)->delete(); it also works
        }else{
            echo 'group not available';
        }
    }

    public function createAttributeGroup($attribute_group_name ,$attribute_group_id, $attributeSetId) {
        $model = Mage::getModel('eav/entity_attribute_group');
        $mapobj = Mage::getModel('customimport/customimport');
        $attributeGroupId = $mapobj->getAttributeGroupByExternalId($attribute_group_id, $attributeSetId);
        if(isset($attributeGroupId) && !empty($attributeGroupId)) {
            $model->load($attributeGroupId);
            $oldGroupName =  $model->getAttributeGroupName();
            if($oldGroupName != $attribute_group_name){  // if name has been updated
                $model->setAttributeGroupName($attribute_group_name);
                if(!$model->itemExists()){
                    $model->save();
                }
            }
        }
        else{
            $model->setAttributeGroupName($attribute_group_name)
                ->setAttributeSetId($attributeSetId);
            if( $model->itemExists() ) {
            } else {
                try {
                  //  $model->save()->getAttributeGroupId();
                     $model->save();
                } catch (Exception $e) {
                    Mage::getSingleton('adminhtml/session')->addError(Mage::helper('catalog')->__('An error occurred while saving this group.'));
                }
            }
            $attributeGroupId = $mapobj->getGroupIdUsingSetId($attribute_group_name,$attributeSetId);
            $mapobj->mapAttributeGroup($attribute_group_id,$attributeGroupId,$attributeSetId); // externalid, magentoid
        }
    }
    public function importAttributeInsideGroup($attribute_group_id, $attributeSetId , $attribute_code,$attribute_sort_order) {
        $mapobj = Mage::getModel('customimport/customimport');
        $attributeGroupId = $mapobj->getAttributeGroupByExternalId($attribute_group_id, $attributeSetId);  	// $attribute_group_id is external group id

        if($attributeGroupId){
            $setup = new Mage_Eav_Model_Entity_Setup('core_setup');
            $attribute_id=$setup->getAttributeId('catalog_product', $attribute_code);
            $attribute_exists = $mapobj->isAttributeExistsInGroup($attribute_id, $attributeGroupId);
            if($attribute_exists){
                $mapobj->updateSequenceOfAttribute($attributeGroupId, $attribute_id, $attribute_sort_order);
            }else{
                $setup->addAttributeToGroup('catalog_product', $attributeSetId, $attributeGroupId, $attribute_id, $attribute_sort_order);
            }
        }
    }

    public function getAttributeGroupId($attribute_group_name , $attribute_set_name) {
        $entityTypeId = $this->getEntityTypeId();
        $attributeSetId = $this->getAttributeSetId($attribute_set_name);
        $installer = $this->getInstaller();//new Mage_Eav_Model_Entity_Setup(‘core_setup’);
        $attributeGroupObject = new Varien_Object($installer->getAttributeGroup($entityTypeId ,$attributeSetId, $attribute_group_name));
        return $attributeGroupId = $attributeGroupObject->getAttributeGroupId();
    }

    public function getGroupIdUsingSetId($attribute_group_name , $attributeSetId) {
        $entityTypeId = $this->getEntityTypeId();
        $installer = $this->getInstaller();//new Mage_Eav_Model_Entity_Setup(‘core_setup’);
        $attributeGroupObject = new Varien_Object($installer->getAttributeGroup($entityTypeId ,$attributeSetId, $attribute_group_name));
        return $attributeGroupId = $attributeGroupObject->getAttributeGroupId();
    }

    public function getEntityTypeId() {
        return $entityTypeId = Mage::getModel('catalog/product')->getResource()->getTypeId();
    }

    public function getInstaller() {
        return new Mage_Catalog_Model_Resource_Eav_Mysql4_Setup('core_setup');
    }
}
?>