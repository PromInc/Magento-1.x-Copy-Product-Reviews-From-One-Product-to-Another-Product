# Magento 1.x Copy Product Reviews From One Product to Another Product
Copy product reviews from one product to another via the command line for Magento 1.x

## Use Case
Occasionally you'll find a need to copy product reviews from one product to another.  Examples:

- Change product packaging but same core product and you choose to add this to Magento as a new SKU
- You break out a grouped product to individually visible simple products

## How To Use
This tool is run from the commandline, so you will need shell access to your server.

Copy this file to the file system (best to NOT put it into the Magento directory).

Run the following command from the command line:

```php copy_product_reviews_to_product.php 2065 7232 /var/www/vhosts/mydomain.com/html/```

### Arguments
| Argument Nubmer | Description |
|---|---|
| 1 | Product ID of the *old* product - the product that contains the reviews you wish to copy |
| 2 | Product ID of the *new* product - the product to copy the reivews to |
| 3 | Path on the filesystem to the root of your Magento directory |
