# PayZen-PHP-SOAP-V5-createPayment-example
Example of PHP code using PayZen SOAP V5 webservices - createPayment request


## Introduction
The code presented here is a demonstration of the implementation of the SOAP v5 PayZen webservices, aimed to ease its use and learning.

This code only supports the `createPayment` request, but shows how a PayZen request and its answer can be handled.


## Contents
This code is divided in three parts:
* payzen.soap-v5.createPayment.example.php, the main file, entry point of the process
* payzenSoapV5ToolBox.php, the core file, defining an utility class encapsulating all the PayZen logics
* UUID.php, an utility tool handling the generation of valid UUID, took from [https://gist.github.com/dahnielson/508447](https://gist.github.com/dahnielson/508447).


## The first use
1. Place the files on the same directory
2. In `payzen.soap-v5.createPayment.example.php`, replace the occurences of `[***CHANGE-ME***]` by the actual values of your PayZen account
3. Execute:
> php payzen.soap-v5.createPayment.example.php
to perform the createPayment request, in "TEST" mode.


## The next steps
You can follow the on-file documentation in `payzen.soap-v5.createPayment.example.php` to change the properties of the payment you want to initiate, like the amount or the informations of the customer payment card.

You will also find here the instructions on how to plug the toolbox logging process to your own, and finally, you can change the `TEST` parameter to `PRODUCTION` to switch to _real_ payment mode, with *all* the caution this decision expects.


## Note
* The documentation used to write this code was [Guide technique d’implémentation des Web services V5, v1.4](https://payzen.eu/wp-content/uploads/2015/09/Guide_technique_d_implementation_Webservice_V5_v1.4_Payzen.pdf) (FRENCH)





