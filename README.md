Magento-Catalog-Import
======================

Incremental import of categories, attributes and products into Magento

**How to get rolling :**

1. Install the extension as you would any standard Magento extension by merging it with your Magento root.
2. To give it a trial run, use the gec-catalog-export-draft.xml file to try your first import.
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

6. Setup import notification email address at Configuration >> Store Email Addresses >> Custom Email 1. (The Magento instance should have outgoing mail settings setup for this to work.)

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
* Recommended settings
 * Safe_mode off
 * Memory_limit no less than 512Mb (preferably 1024)
 * max_execution_time to 3600 (This may be required to increase based on the size of the xml and time it may take to import)
 
**Changelog :**

*[1.0.0.1] - 2013-09-09*

Initial release for Magento 1.7

*[2.0.0.1] - 2015-11-27*

Major bug-fix and feature release (Tested on Magento 1.9.x)

Bug-fixes:

1. The extension is now independent of any frontend theme changes. Earlier theme changes required porting of frontend section to the new theme.

2. Added missing refrential integrity for 'default' attributeset that needs to be associated with products that have an empty attributesetid.

3. Fixed attribute value import for text, textarea and boolean attributes. 

New Features & Improvements:

1. Default turning off visibility in search results for simple products associated to configurable ones.

2. Import of special price and related dates to enable scheduling of sale pricing.

3. Inventory control - ATP quantity and ability to control backordering of products.

4. Several major logging improvements. 

5. Improved error-handling, data error tolerance and reporting for missing elements in the import file. Report the issue and continue with the next element instead of aborting the process.

6. Performance improvements related to categories and attributes import.

**Upcoming:**

1. Support for import of Packaged Products (a.k.a. Marketing Package Pick Assembly)


