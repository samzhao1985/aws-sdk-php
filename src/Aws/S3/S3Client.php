<?php
/**
 * Copyright 2010-2012 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 * http://aws.amazon.com/apache2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */

namespace Aws\S3;

use Aws\Common\Client\AbstractClient;
use Aws\Common\Client\ClientBuilder;
use Aws\Common\Credentials\CredentialsInterface;
use Aws\Common\Enum\ClientOptions as Options;
use Aws\S3\Exception\AccessDeniedException;
use Aws\S3\Exception\Parser\S3ExceptionParser;
use Aws\S3\Exception\S3Exception;
use Aws\S3\Model\ClearBucket;
use Aws\S3\S3Signature;
use Aws\S3\S3SignatureInterface;
use Guzzle\Common\Collection;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Service\Command\CommandInterface;
use Guzzle\Service\Command\Factory\AliasFactory;

/**
 * Client to interact with Amazon S3
 *
 * @method array abortMultipartUpload (array $args = array()) {@command s3 AbortMultipartUpload}
 * @method array completeMultipartUpload (array $args = array()) {@command s3 CompleteMultipartUpload}
 * @method array createBucket (array $args = array()) {@command s3 CreateBucket}
 * @method array deleteBucket (array $args = array()) {@command s3 DeleteBucket}
 * @method array deleteBucketLifecycle (array $args = array()) {@command s3 DeleteBucketLifecycle}
 * @method array deleteBucketNotification (array $args = array()) {@command s3 DeleteBucketNotification}
 * @method array deleteBucketPolicy (array $args = array()) {@command s3 DeleteBucketPolicy}
 * @method array deleteBucketWebsite (array $args = array()) {@command s3 DeleteBucketWebsite}
 * @method array deleteMultipleObjects (array $args = array()) {@command s3 DeleteMultipleObjects}
 * @method array deleteObject (array $args = array()) {@command s3 DeleteObject}
 * @method array getBucketAcl (array $args = array()) {@command s3 GetBucketAcl}
 * @method array getBucketLifecycle (array $args = array()) {@command s3 GetBucketLifecycle}
 * @method array getBucketLocation (array $args = array()) {@command s3 GetBucketLocation}
 * @method array getBucketLogging (array $args = array()) {@command s3 GetBucketLogging}
 * @method array getBucketNotification (array $args = array()) {@command s3 GetBucketNotification}
 * @method array getBucketObjectVersions (array $args = array()) {@command s3 GetBucketObjectVersions}
 * @method array getBucketPolicy (array $args = array()) {@command s3 GetBucketPolicy}
 * @method array getBucketRequestPayment (array $args = array()) {@command s3 GetBucketRequestPayment}
 * @method array getBucketVersioning (array $args = array()) {@command s3 GetBucketVersioning}
 * @method array getBucketWebsite (array $args = array()) {@command s3 GetBucketWebsite}
 * @method array getObject (array $args = array()) {@command s3 GetObject}
 * @method array getObjectAcl (array $args = array()) {@command s3 GetObjectAcl}
 * @method array getObjectTorrent (array $args = array()) {@command s3 GetObjectTorrent}
 * @method array headBucket (array $args = array()) {@command s3 HeadBucket}
 * @method array headObject (array $args = array()) {@command s3 HeadObject}
 * @method array initiateMultipartUpload (array $args = array()) {@command s3 InitiateMultipartUpload}
 * @method array listBuckets (array $args = array()) {@command s3 ListBuckets}
 * @method array listMultipartUploads (array $args = array()) {@command s3 ListMultipartUploads}
 * @method array listObjects (array $args = array()) {@command s3 ListObjects}
 * @method array listParts (array $args = array()) {@command s3 ListParts}
 * @method array putBucketAcl (array $args = array()) {@command s3 PutBucketAcl}
 * @method array putBucketLifecycle (array $args = array()) {@command s3 PutBucketLifecycle}
 * @method array putBucketLogging (array $args = array()) {@command s3 PutBucketLogging}
 * @method array putBucketNotification (array $args = array()) {@command s3 PutBucketNotification}
 * @method array putBucketPolicy (array $args = array()) {@command s3 PutBucketPolicy}
 * @method array putBucketRequestPayment (array $args = array()) {@command s3 PutBucketRequestPayment}
 * @method array putBucketVersioning (array $args = array()) {@command s3 PutBucketVersioning}
 * @method array putBucketWebsite (array $args = array()) {@command s3 PutBucketWebsite}
 * @method array putObject (array $args = array()) {@command s3 PutObject}
 * @method array putObjectAcl (array $args = array()) {@command s3 PutObjectAcl}
 * @method array putObjectCopy (array $args = array()) {@command s3 PutObjectCopy}
 * @method array uploadPart (array $args = array()) {@command s3 UploadPart}
 * @method array uploadPartCopy (array $args = array()) {@command s3 UploadPartCopy}
 */
class S3Client extends AbstractClient
{
    /**
     * @var array Aliases for S3 operations
     */
    protected static $commandAliases = array(
        // REST API Docs Aliases
        'GetService' => 'ListBuckets',
        'GetBucket'  => 'ListObjects',
        'PutBucket'  => 'CreateBucket',

        // SDK 1.x Aliases
        'GetBucketHeaders'              => 'HeadBucket',
        'GetObjectHeaders'              => 'HeadObject',
        'SetBucketAcl'                  => 'PutBucketAcl',
        'CreateObject'                  => 'PutObject',
        'DeleteObjects'                 => 'DeleteMultipleObjects',
        'CopyObject'                    => 'PutObjectCopy',
        'SetObjectAcl'                  => 'PutObjectAcl',
        'GetLogs'                       => 'GetBucketLogging',
        'GetVersioningStatus'           => 'GetBucketVersioning',
        'SetBucketPolicy'               => 'PutBucketPolicy',
        'CreateBucketNotification'      => 'PutBucketNotification',
        'GetBucketNotifications'        => 'GetBucketNotification',
        'CopyPart'                      => 'UploadPartCopy',
        'CreateWebsiteConfig'           => 'PutBucketWebsite',
        'GetWebsiteConfig'              => 'GetBucketWebsite',
        'DeleteWebsiteConfig'           => 'DeleteBucketWebsite',
        'CreateObjectExpirationConfig'  => 'PutBucketLifecycle',
        'GetObjectExpirationConfig'     => 'GetBucketLifecycle',
        'DeleteObjectExpirationConfig'  => 'DeleteBucketLifecycle',
    );

    /**
     * @inheritdoc
     */
    protected $directory = __DIR__;

    /**
     * Factory method to create a new Amazon S3 client using an array of configuration options.
     *
     * The following array keys and values are available options:
     *
     * - Credential options (`key`, `secret`, and optional `token` OR `credentials` is required)
     *     - key: AWS Access Key ID
     *     - secret: AWS secret access key
     *     - credentials: You can optionally provide a custom `Aws\Common\Credentials\CredentialsInterface` object
     *     - token: Custom AWS security token to use with request authentication
     *     - token.ttd: UNIX timestamp for when the custom credentials expire
     *     - credentials.cache: Used to cache credentials when using providers that require HTTP requests. Set the true
     *           to use the default APC cache or provide a `Guzzle\Common\Cache\CacheAdapterInterface` object.
     *     - credentials.cache.key: Optional custom cache key to use with the credentials
     *     - credentials.client: Pass this option to specify a custom `Guzzle\Http\ClientInterface` to use if your
     *           credentials require a HTTP request (e.g. RefreshableInstanceProfileCredentials)
     * - Region and Endpoint options (a `region` and optional `scheme` OR a `base_url` is required)
     *     - region: Region name (e.g. 'us-east-1', 'us-west-1', 'us-west-2', 'eu-west-1', etc...)
     *     - scheme: URI Scheme of the base URL (e.g. 'https', 'http').
     *     - base_url: Instead of using a `region` and `scheme`, you can specify a custom base URL for the client
     *     - endpoint_provider: Optional `Aws\Common\Region\EndpointProviderInterface` used to provide region endpoints
     * - Generic client options
     *     - ssl.cert: Set to true to use the bundled CA cert or pass the full path to an SSL certificate bundle. This
     *           option should be used when you encounter curl error code 60.
     *     - curl.CURLOPT_VERBOSE: Set to true to output curl debug information during transfers
     *     - curl.*: Prefix any available cURL option with `curl.` to add cURL options to each request.
     *           See: http://www.php.net/manual/en/function.curl-setopt.php
     *     - service.description.cache: Optional `Guzzle\Common\Cache\CacheAdapterInterface` object to use to cache
     *           service descriptions
     *     - service.description.cache.ttl: Optional TTL used for the service description cache
     * - Amazon S3 specific options
     *     - bucket.path_style: Set to true to force path style requests when possible
     * - Signature options
     *     - signature: You can optionally provide a custom signature implementation used to sign requests
     * - Exponential backoff options
     *     - client.backoff.logger: `Guzzle\Common\Log\LogAdapterInterface` object used to log backoff retries. Use
     *           'debug' to emit PHP warnings when a retry is issued.
     *     - client.backoff.logger.template: Optional template to use for exponential backoff log messages. See
     *           `Guzzle\Http\Plugin\ExponentialBackoffLogger` for formatting information.
     *
     * @param array|Collection $config Client configuration data
     *
     * @return self
     */
    public static function factory($config = array())
    {
        $client = ClientBuilder::factory(__NAMESPACE__)
            ->setConfig($config)
            ->setConfigDefaults(array(
                'curl.blacklist' => array(CURLOPT_ENCODING, 'header.Accept'),
                Options::SCHEME  => 'https',
                Options::SERVICE => 's3'
            ))
            ->setSignature(new S3Signature())
            ->setExceptionParser(new S3ExceptionParser())
            ->build();

        return $client;
    }

    /**
     * @param CredentialsInterface $credentials AWS credentials
     * @param S3SignatureInterface $signature   Amazon S3 Signature implementation
     * @param Collection           $config      Configuration options
     */
    public function __construct(CredentialsInterface $credentials, S3SignatureInterface $signature, Collection $config)
    {
        parent::__construct($credentials, $signature, $config);

        // Add aliases for some S3 operations
        $this->getCommandFactory()->add(
            new AliasFactory($this, self::$commandAliases),
            'Guzzle\Service\Command\Factory\ServiceDescriptionFactory'
        );
    }

    /**
     * Find out if a string is a valid name for an Amazon S3 bucket.
     *
     * @param string $bucket The name of the bucket to check.
     *
     * @return bool TRUE if the bucket name is valid or FALSE if it is invalid.
     */
    public static function isValidBucketName($bucket)
    {
        $bucketLen = strlen($bucket);
        if (!$bucket || $bucketLen < 3 || $bucketLen > 63
            // Cannot start or end with a '.'
            || $bucket[0] == '.'
            || $bucket[$bucketLen - 1] == '.'
            // Cannot look like an IP address
            || preg_match('/^\d+\.\d+\.\d+\.\d+$/', $bucket)
            // Cannot include special characters, must start and end with lower alnum
            || !preg_match('/^[a-z0-9]([a-z0-9\\-.]*[a-z0-9])?$/', $bucket)) {
            return false;
        }

        return true;
    }

    /**
     * Create a pre-signed URL for a request
     *
     * @param RequestInterface $request Request to generate the URL for
     * @param int|string       $expires The Unix timestamp to expire at or a string that can be evaluated by strtotime
     *
     * @return string
     */
    public function createPresignedUrl(RequestInterface $request, $expires)
    {
        if (!is_numeric($expires)) {
            $expires = strtotime($expires);
        }

        $copy = clone $request;
        $copy->getQuery()
            ->set('AWSAccessKeyId', $this->credentials->getAccessKeyId())
            ->set('Expires', $expires)
            ->set('Signature', $this->signature->signString(
                $this->signature->createCanonicalizedString($request, $expires),
                $this->credentials
            ));

        return $copy->getUrl();
    }

    /**
     * Helper used to clear the contents of a bucket. Use the {@see ClearBucket} object directly
     * for more advanced options and control.
     *
     * @param string $bucket Name of the bucket to clear.
     *
     * @return int Returns the number of deleted keys
     */
    public function clearBucket($bucket)
    {
        $clear = new ClearBucket($this, $bucket);

        return $clear->clear();
    }

    /**
     * Determines whether or not a bucket exists by name
     *
     * @param string $bucket    The name of the bucket
     * @param bool   $accept403 Set to true if 403s are acceptable
     * @param array  $options   Additional options to add to the executed command
     *
     * @return bool
     */
    public function doesBucketExist($bucket, $accept403 = true, array $options = array())
    {
        return $this->checkExistenceWithCommand(
            $this->getCommand(
                'HeadBucket',
                array_merge($options, array(
                    'bucket' => $bucket
                ))
            ), $accept403
        );
    }

    /**
     * Determines whether or not an object exists by name
     *
     * @param string $bucket  The name of the bucket
     * @param string $key     The key of the object
     * @param array  $options Additional options to add to the executed command
     *
     * @return bool
     */
    public function doesObjectExist($bucket, $key, array $options = array())
    {
        return $this->checkExistenceWithCommand(
            $this->getCommand(
                'HeadObject',
                array_merge($options, array(
                    'bucket' => $bucket,
                    'key'    => $key
                ))
            )
        );
    }

    /**
     * Determines whether or not a bucket policy exists for a bucket
     *
     * @param string $bucket  The name of the bucket
     * @param array  $options Additional options to add to the executed command
     *
     * @return bool
     */
    public function doesBucketPolicyExist($bucket, array $options = array())
    {
        return $this->checkExistenceWithCommand(
            $this->getCommand(
                'GetBucketPolicy',
                array_merge($options, array(
                    'bucket' => $bucket
                ))
            )
        );
    }

    /**
     * Determines whether or not a resource exists using a command
     *
     * @param CommandInterface $command   Command used to poll for the resource
     * @param bool             $accept403 Set to true if 403s are acceptable
     *
     * @return bool
     * @throws S3Exception if there is an unhandled exception
     */
    protected function checkExistenceWithCommand(CommandInterface $command, $accept403 = false)
    {
        try {
            $exists = (bool) $command->execute();
        } catch (AccessDeniedException $e) {
            $exists = (bool) $accept403;
        } catch (S3Exception $e) {
            $exists = false;
            if ($e->getResponse()->getStatusCode() >= 500) {
                // @codeCoverageIgnoreStart
                throw $e;
                // @codeCoverageIgnoreEnd
            }
        }

        return $exists;
    }
}