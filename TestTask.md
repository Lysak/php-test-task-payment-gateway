### SharpMinds - Test task for a Middle PHP Developer position

We are working with a hypothetical payment gateway that uses mutual TLS (mTLS) for transport-level security and HMAC signatures to ensure payload data integrity.  
We need a PHP Composer package that implements this functionality.

The package must be able to:

* Accept as input:  
  an API endpoint URL (example: [https://test.com/api/check](https://test.com/api/check)),  
  a one-dimensional array with test data (example: \['transaction\_id' \=\> '12345', 'amount' \=\> '99.99', 'currency' \=\> 'USD'\])
* Establish an mTLS connection by:  
  providing a client certificate (e.g., .pem, .crt),  
  validating the server certificate
* Send a GET HTTP request with the provided test data to the specified API endpoint and:  
  compute an HMAC signature from the payload and a shared secret,  
  attach the signature to the request using a standard Authorization or X-Signature header.
* Upon receiving a response:  
  ensure that the HTTP response code is in the range of 20x, or report an error

Additional Notes:

* The package should read configuration (e.g., certificate paths, HMAC secret) from a .env file.
* The code should follow PSR-4 autoloading and be structured for testability.
* The package should have at least one unit test (e.g., for HMAC computation).
* The package should have at least one integration test that sends a real HTTP request using mTLS and validates the response (provided the tester fills in real paths/secrets in .env).
* For convenience, it is recommended to put the package on a GitHub or Bitbucket public repository.
* You are not expected to interact with a real payment gateway. However, for mTLS testing purposes, we recommend using the BadSSL website. It supports only GET requests.  
  You may download client certificates from here: [https://badssl.com/download/](https://badssl.com/download/)  
  The passphrase for the private key is "badssl.com". Example curl command:  
  *curl \-v \--cert badssl.com-client-cert.pem \--key badssl.com-client-key.pem "[https://client.badssl.com/?transaction\_id=12345\&amount=99.99\&currency=USD"](https://client.badssl.com/?transaction_id=12345&amount=99.99&currency=USD%22) \-H "X-Signature: abcdef1234567890"*
* mTLS explanation: [https://developer.mastercard.com/platform/documentation/authentication/using-mtls-to-access-mastercard-apis/](https://developer.mastercard.com/platform/documentation/authentication/using-mtls-to-access-mastercard-apis/)
* Suggested HMAC implementation: [https://developer.americanexpress.com/documentation/api-security/hmac](https://developer.americanexpress.com/documentation/api-security/hmac) (you can choose another one)
* You may use any PHP library or a package that you need

To test the integration, the reviewer will:

* Clone the repository
* Place certificates and the private key locally
* Fill in the .env with proper values
* Run ./vendor/bin/phpunit (or something similar) to execute both unit and integration tests

