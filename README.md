# moyasar-magento-plugin
[Moyasar](https://moyasar.com) Payment Gateway plugin for Magento 2

## Installation

1. Download the Payment Module archive, unpack it and upload Moyasar contents to <root>/app/code/Moyasar/Mysr of your Magento 2 installation.
2. Enable the extension and clear static view files:
```sh
$ php bin/magento module:enable Moyasar_Mysr --clear-static-content
```
3. Register the extension:
```sh
$ php bin/magento setup:upgrade
```
4. Deploy Magento Static Content:
```sh
$ php bin/magento setup:static-content:deploy
```
## Configration

* Login inside the __Admin Panel__ and go to ```Stores``` -> ```Configuration``` -> ```Sales``` -> ```Payment Methods```
* Find ```Moyasar``` payment methods (Credit Cards or Sadad) in the list of modules.
* Set ```Enabled``` to ```Yes``` and put your `publishable_key` then ```save config```.
