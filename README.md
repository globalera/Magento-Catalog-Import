Magento Catalog Import
======================

Incremental import of categories, attributes and products into Magento

**How to get rolling :**

1. Install the extension as you would any standard Magento extension by merging it with your Magento root.

2. To give it a trial run, use the latest [gec-catalog-export-draft.xml](https://github.com/globalera/Magento-Catalog-Import/blob/master/gec-catalog-export-draft.xml) file to try your first import. The XML import specification is available [herein](https://github.com/globalera/Magento-Catalog-Import/blob/master/catalog-import-xml-specification.ods).
3. Admin has access to the Catalog import screen at Backend >> Catalog >> Catalog Import

   If you want to allow it's access for specific user, You can do so by alloting a user a specific role - "Catalog Import". 
   
    Step 1: Admin >> Systems >> Permissions >> Users >> [Create user]
   
    Step 2: Admin >> Systems >> Permissions >> Roles >> Add New Roles >> Role Resources >> check [Catalog Import]

4. Externally imported image URL can now be configured as priority image or as a fallback image vis-a-vis internal image.
   
    Configuration >> Catalog >> Product Image Usage

    Internal Image Usage (Yes / No) : Setting as 'No' prioritises the external image URLs over the internal images. When 'Yes',     the uploaded internal images are given higher priority. 

5. You can automate the setup of "New Products" for newly imported products by configuring these parameters -
    
    System >> Configuration >> Catalog >> New Product Configuration

    Days: Specify number of days to determine 'New to Date' of a newly imported product. For example, specifying 45 shall add 45 days to the day the product was first imported. Leaving it blank shall delegate control to default Magento functionality for identifying new products.
    
    Limit: Limits the number of new products fetched. Leave blank if you wish to display all products (not recommended).

6. Setup import notification email address at Configuration >> Store Email Addresses >> Custom Email 1. (The Magento instance should have outgoing mail settings setup for this to work. For newbies, we recommend getting started with the free [Aschroder Magento SMTP Extension](https://github.com/aschroder/Magento-SMTP-Pro-Email-Extension).) 

**Requirements :**

* Magento version 1.7.0.0 or higher
* PHP Compatibility:
 * 5.4.0 - 5.5.30
* Required PHP extensions:
 * PDO_MySQL
 * simplexml
 * mcrypt
 * hash
 * GD
 * DOM
 * iconv
 * curl
* Recommended settings:
 * Safe_mode off
 * Memory_limit no less than 512Mb (preferably 1024)
 * max_execution_time to 3600 (This may be required to increase based on the size of the xml and time it may take to import)
 
**Changelog :**

Please refer https://github.com/globalera/Magento-Catalog-Import/releases

**Support :**

Do feel free to post your technical queries on the [Issues](https://github.com/globalera/Magento-Catalog-Import/issues) page.

In case you need help with generation of the import XML from your existing/legacy systems, do contact us via [our website](http://globalera.com/contact).
