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

### Automatic Payment Check (Job Scheduling)
In order to allow the system to automatically verify orders that was not
redirected back the store, you have to make sure that cron jobs are configured correctly

```bash
bin/magento cron:install
``` 

If you have already configured cron jobs, there is no need to use the previous command

#### How Automatic Payment Checking Work
The cron job will run every minute scanning for `new` orders, if it finds
any then it will fetch that order's payment from Moyasar to see if it's payed.

If an order payment is `initiated` then it will be checked again after 3 minutes 


## Configration

* Login inside the __Admin Panel__ and go to ```Stores``` -> ```Configuration``` -> ```Sales``` -> ```Payment Methods```
* Find ```Moyasar``` payment methods (Credit Cards or Sadad) in the list of modules.
* Set ```Enabled``` to ```Yes``` and put your `publishable_key` then ```save config```.


## Apple Pay Setup

To enable Apple Pay, you have to generate a Merchant Validation Certificate from you Apple Account,
Apple usually send these certificates in PKCS11 format which is not supported by PHP. To convert these certificates, please use the following:

```sh
openssl x509 -inform der -in merchant_id.cer -out merchant_id.pem
openssl pkcs12 -nocerts -nodes -in merchant_id.p12 -out merchant_id.key
```

Also, you need to setup you secret and publishable key in both Apple Pay configuration section and Credit Card section (This is important)


## FAQ

Question: I get "Invalid parameter given. A valid $fileId[tmp_name] is expected." when trying to upload certificate for Apple Pay
Answer: This is because `sys_temp_dir` PHP setting is not configured, update `php.ini` and set `sys_temp_dir` to `/tmp` or `/private/tmp` on macOS

Question: I get a permission error when trying to upload certificate and key.
Asnwer: Make sure your server can write to the following directory: `var/moyasar/apple-pay/certificates/default`
