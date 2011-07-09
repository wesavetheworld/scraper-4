<?php if (!class_exists('CFRuntime')) die('No direct access allowed.');
/**
 * Stores your AWS account information. Add your account information, and then rename this file
 * to 'config.inc.php'.
 *
 * @version 2011.06.02
 * @license See the included NOTICE.md file for more information.
 * @copyright See the included NOTICE.md file for more information.
 * @link http://aws.amazon.com/php/ PHP Developer Center
 * @link http://aws.amazon.com/security-credentials AWS Security Credentials
 */


/**
 * Amazon Web Services Key. Found in the AWS Security Credentials. You can also pass this value as the first
 * parameter to a service constructor.
 */
define('AWS_KEY', 'AKIAJTK4FD7JDZQ5ESPA');

/**
 * Amazon Web Services Secret Key. Found in the AWS Security Credentials. You can also pass this value as
 * the second parameter to a service constructor.
 */
define('AWS_SECRET_KEY', 'lWvBq2r6zc8tYNDa7N1Gzbig8xeMJz/fLfiVOHqv');

/**
 * Amazon Account ID without dashes. Used for identification with Amazon EC2. Found in the AWS Security
 * Credentials.
 */
define('AWS_ACCOUNT_ID', '6680-0286-0621');

/**
 * Your CanonicalUser ID. Used for setting access control settings in AmazonS3. Found in the AWS Security
 * Credentials.
 */
define('AWS_CANONICAL_ID', ' 2e271a0773aec1e3eca30e756041ab736406effb3893a9d23d00b4218cb83e9d
');

/**
 * Your CanonicalUser DisplayName. Used for setting access control settings in AmazonS3. Found in the AWS
 * Security Credentials (i.e. "Welcome, AWS_CANONICAL_NAME").
 */
define('AWS_CANONICAL_NAME', 'Ryan vanniekerk');

/**
 * Determines which Cerificate Authority file to use.
 *
 * A value of boolean `false` will use the Certificate Authority file available on the system. A value of
 * boolean `true` will use the Certificate Authority provided by the SDK. Passing a file system path to a
 * Certificate Authority file (chmodded to `0755`) will use that.
 *
 * Leave this set to `false` if you're not sure.
 */
define('AWS_CERTIFICATE_AUTHORITY', false);

/**
 * 12-digit serial number taken from the Gemalto device used for Multi-Factor Authentication. Ignore this
 * if you're not using MFA.
 */
define('AWS_MFA_SERIAL', '');

/**
 * Amazon CloudFront key-pair to use for signing private URLs. Found in the AWS Security Credentials. This
 * can be set programmatically with <AmazonCloudFront::set_keypair_id()>.
 */
define('AWS_CLOUDFRONT_KEYPAIR_ID', 'APKAIW3SKXMD5UZSHZLQ');

/**
 * The contents of the *.pem private key that matches with the CloudFront key-pair ID. Found in the AWS
 * Security Credentials. This can be set programmatically with <AmazonCloudFront::set_private_key()>.
 */
define('AWS_CLOUDFRONT_PRIVATE_KEY_PEM', 'MIICXgIBAAKBgQDORgBofBY70a092Se/JiiqaeF3+yXWgXGamtcE2AetiBacvq2p
pC3vRahZsOr2deNk+2VTdA3XYHalcIr4NdA01cuCAW4QH9xdNimgNNV5FbtirGYw
UYGDtDIMHKjqP6CrNWMKgJ9sQ6whX+C9zBpqlA9D3FZt3a7ncUcztbSbrwIDAQAB
AoGATBx8ThFrSstFd0rHZbq4ypii/1iGT64XswprSHhF7PwIC/I3th7EbENxqRak
vCgnrb0tWCu8Z7pKTSVHweppVkvqwLuA3rzMNLoLf2SNP6nTB0QGhhIruYHYunuh
H+sEPCRcn4oF57ZY5neMfKUysKsIksXIYHODPH7E/sW/c+ECQQDpPjZN6KxQeDwr
WEk6PJrXPZqv05TheWlm0MV3uvfn7Ri0t96580mpk4+ryM2DLg906exb3uoO0CrO
NFrN3L/nAkEA4mYpWzawKaQDKjGDL+dExcHBZAZr1Xj6a6z8flCCvUlrwAfYZA41
0vsRHdH7UbeNblx6Tlwi0Ri1NI6gz6Ps+QJBAI6M3Tekepv7wBplrOuQ2rmuBvpq
/9UGFSsncWiJtrXirHTW46MWU/D2JJrC8Qe5gOzdgv1rMfW3uFGqocyrnAUCQQCQ
Dbka1L1agYWR/1cdz+Wufw5yerN6bTPJ95PhO5E0p9brpRJG99O/nwjRFJ746/YN
1sHrwixVJ4eFHYutEvzhAkEAwUKCuNaQyMo6zpNzjbcHMwzGn5BpfrTm1yLtjk0X
73XU9l5ajNr1/O5uGkObtF+wR6DF9zR0b821QDidu9d6sQ');

/**
 * Set the value to true to enable autoloading for classes not prefixed with "Amazon" or "CF". If enabled,
 * load `sdk.class.php` last to avoid clobbering any other autoloaders.
 */
define('AWS_ENABLE_EXTENSIONS', 'false');
