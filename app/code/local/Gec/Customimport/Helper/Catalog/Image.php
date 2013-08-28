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

class Gec_Customimport_Helper_Catalog_Image extends Mage_Catalog_Helper_Image
{

    protected $_requestedImage;

    /**
     * Reset all previous data
     *
     * @return Mage_Catalog_Helper_Image
     */
    protected function _reset()
    {
        $this->_model = null;
        $this->_scheduleResize = false;
        $this->_scheduleRotate = false;
        $this->_angle = null;
        $this->_watermark = null;
        $this->_watermarkPosition = null;
        $this->_watermarkSize = null;
        $this->_watermarkImageOpacity = null;
        $this->_product = null;
        $this->_imageFile = null;
        $this->_requestedImage = null;
        return $this;
    }

    /**
     * Initialize Helper to work with Image
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string $attributeName
     * @param mixed $imageFile
     * @return Mage_Catalog_Helper_Image
     */
    public function init(Mage_Catalog_Model_Product $product, $attributeName, $imageFile=null)
    {
        $this->_reset();
        $this->_setModel(Mage::getModel('catalog/product_image'));
        $this->_getModel()->setDestinationSubdir($attributeName);
        $this->setProduct($product);

        $this->_requestedImage = $attributeName;

        $this->setWatermark(
            Mage::getStoreConfig("design/watermark/{$this->_getModel()->getDestinationSubdir()}_image")
        );
        $this->setWatermarkImageOpacity(
            Mage::getStoreConfig("design/watermark/{$this->_getModel()->getDestinationSubdir()}_imageOpacity")
        );
        $this->setWatermarkPosition(
            Mage::getStoreConfig("design/watermark/{$this->_getModel()->getDestinationSubdir()}_position")
        );
        $this->setWatermarkSize(
            Mage::getStoreConfig("design/watermark/{$this->_getModel()->getDestinationSubdir()}_size")
        );

        if ($imageFile) {
            $this->setImageFile($imageFile);
        } else {
            // add for work original size
            $this->_getModel()->setBaseFile($this->getProduct()->getData($this->_getModel()->getDestinationSubdir()));
        }
        return $this;
    }

    /**
     * Return Image URL
     *
     * @return string
     */
    public function __toString()
    {
        try {
            // echo $this->_requestedImage;

            $model = $this->_getModel();
            if ($this->getImageFile()) {
                $model->setBaseFile($this->getImageFile());
            } else {
                $model->setBaseFile($this->getProduct()->getData($model->getDestinationSubdir()));
            }

            if($this->_requestedImage == 'image' || $this->_requestedImage == 'small_image' || $this->_requestedImage == 'thumbnail'){
                $external_attribute = 'external_'.$this->_requestedImage;
                $_product = Mage::getModel('catalog/product')->load($this->getProduct()->getId());
                $externalImg = $_product->getData($external_attribute);

            }


            if ($model->isCached()) {
                // return 'http://staging.serenusinfotech.com/smikum/gec/skin/frontend/default/default/images/media/col_left_callout.jpg';
                if($externalImg){
                    return $externalImg;
                }
                return $model->getUrl();
            } else {
                if ($this->_scheduleRotate) {
                    $model->rotate($this->getAngle());
                }

                if ($this->_scheduleResize) {
                    $model->resize();
                }

                if ($this->getWatermark()) {
                    $model->setWatermark($this->getWatermark());
                }

                $url = $model->saveFile()->getUrl();
                if($externalImg){
                    $url =  $externalImg;
                }
            }
        } catch (Exception $e) {
            $url = Mage::getDesign()->getSkinUrl($this->getPlaceholder());
        }
        return $url;
        // return 'http://staging.serenusinfotech.com/smikum/gec/skin/frontend/default/default/images/media/col_left_callout.jpg';
    }

}
