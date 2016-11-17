<?php

/**
 * Created by PhpStorm.
 * User: jasoncarney
 * Date: 15/11/16
 * Time: 12:19 PM
 */
class PremiumPageHandler
{
    /**
     * @param modX $modx
     * @param boolean $debug
     * @param modResource $resource
     * @param int $charge
     */

    private $modx;
    private $resourceGroupPrefix = 'Premium Page %d ';
    private $resource;
    private $charge;

    /**
     * @param modX $modx
     * @param modResource $resource
     */
    public function __construct($modx, $resource = null)
    {
        $this->modx = $modx;
        $this->resource = $resource;
    }

    public function loadModxAPI($context)
    {
        $modx = new modX();
        $modx->initialize($context);
        $this->modx = $modx;
    }

    /**
     * @return boolean false if not premium, true on success
     */
    public function handlePageSave()
    {

        if (!$this->isPremiumPage($this->resource)) {
            return false; //nothing to do
        }

        //attach to relevant resource group
        $this->assignResourceGroup();

        return true;
    }

    /**
     * @param string $token stripe payment token
     * @param string $priceTv name of tv holding the price to charge
     * @param string $pagesSoldTv name of tv holding ID's of pages to grant access to
     * @param int $salesPage ID of pages with pagesSoldTv value we need to inspect
     * @return PaymentResponse
     * @throws Exception 06: non authenticated user
     */
    public function handlePayment($token, $priceTv, $pagesSoldTv, $salesPage)
    {
        /**
         * @var modResource $salesPageResource
         */
        $user = $this->modx->getUser();
        if($user->get('id') == 0){
            throw new Exception('Error 06: You must be logged in to purchase products');
        }
        //try to swap to using modx from the API
        $this->loadModxAPI($this->modx->context->get('key'));

        //check requested purchase is valid.
        $userGroups = $this->getRequestedUserGroups($pagesSoldTv, $salesPage);

        //get payment amount from page
        $this->getPaymentDetails($salesPage, $priceTv);

        //process payment
        $paymentResponse = $this->chargePayment($token);

        //add user to group/s
        foreach ($userGroups as $userGroup){
            $user->joinGroup($userGroup->get('id'));
        }

        $paymentResponse->setUser($user);

        //flush permissions
        $this->flushPermissions();

        //return payment response
        return $paymentResponse;

    }

    private function getPaymentDetails($salesPage, $priceTv)
    {
        $salesPageResource = $this->modx->getObject('modResource', $salesPage);
        if(!$salesPageResource){
            throw new Exception('Error 07: Invalid sales page provided (ID: '.$salesPage.')');
        }

        $saleValue = floatval($salesPageResource->getTVValue($priceTv));

        if(empty($saleValue) || $saleValue < 0){
            throw new Exception('Error 08: Invalid price supplied (ID:'.$salesPage.')');
        }

        //convert to cents
        $this->charge = intval($saleValue * 100);
    }

    /**
     * @param $token
     * @return PaymentResponse
     */
    private function chargePayment($token)
    {
        $paymentHandler = $this->getPaymentProcessor($token);
        $paymentHandler->setCharge($this->charge);
        return $paymentHandler->processPayment();
    }

    /**
     * @param mixed $token payment token
     * @return false|PremiumPageHandlerPaymentGateway
     */
    private function getPaymentProcessor($token)
    {
        /**
         * @var string $type
         */
        $type = $this->modx->getOption('premiumpages.payment_gateway');

        switch ($type){
            case 'stripe':
                $options = array();
                $options['token'] = $token;
                $options['secret_key'] = $this->modx->getOption('premiumpages.stripe_secret_key');
                return new PremiumPageStripeGateway($options);
                break;
            default:
                return false;
                break;
        }
    }

    /**
     * @param string $pagesSoldTv
     * @param string $salesPage
     * @return modUserGroup[]
     * @throws Exception 03: page not found
     * @throws Exception 04: No products
     * @throws Exception 05: Product for ID does not exist
     */
    private function getRequestedUserGroups($pagesSoldTv, $salesPage)
    {
        /**
         * @var modResource $salesResource
         */
        //grab our sales page
        $salesResource = $this->modx->getObject('modResource', intval($salesPage));
        if (!$salesResource instanceof modResource) {
            throw new Exception('Error 03: Sales page not found (ID: ' . $salesPage . ')');
        }

        //grab which products we're buying
        $products = $salesResource->getTVValue($pagesSoldTv);
        $products = explode(',', $products);

        if (count($products) < 1) {
            throw new Exception('Error 04: No Product\'s associated with given sales page (ID: ' . $salesPage . ')');
        }
        //check page exists, check user group exists
        $userGroups = array();
        foreach ($products as $productId) {
            $product = $this->modx->getObject('modResource', intval($productId));

            if(empty($product)){
                throw new Exception('Error 05: Product not found (ID: '. $productId . ')');
            }

            $resourceGroup = $this->getResourceGroup($product->get('id'));

            if($resourceGroup instanceof modResourceGroup){
                $userGroup = $this->modx->getObject('modUserGroup', array(
                    'name' => $resourceGroup->get('name')
                ));

                if(empty($userGroup)){
                    throw new Exception('Error 06: No user group found (Name: '. $resourceGroup->get('name') .')');
                }

                $userGroups[] = $userGroup;
            }
        }
        return $userGroups;
    }

    /**
     * @throws Exception 02: No resource group
     */
    private function assignResourceGroup()
    {
        $resourceGroup = $this->getResourceGroup();
        if (!$resourceGroup instanceof modResourceGroup) {
            //none found, make it
            $name = $this->getPrefix() . $this->resource->get('pagetitle');
            $resourceGroup = $this->createPremiumGroup($name);
        }
        $resourceGroupName = $resourceGroup->get('name');
        if (!$this->resource->isMember($resourceGroupName)) {
            $this->resource->joinGroup($resourceGroup);
        }
    }

    /**
     * @description Will return an existing resource group
     *
     * @return modResourceGroup|false false if not found
     * @var xPDOQuery $query ;
     * @throws Exception 01: too many results
     */
    private function getResourceGroup($product = null)
    {

        $prefix = $this->getPrefix($product);
        $query = $this->modx->newQuery('modResourceGroup');
        $query->where(array(
            'name:LIKE' => $prefix . '%'
        ));

        $results = $this->modx->getCollection('modResourceGroup', $query);

        switch (count($results)) {
            case 1:
                reset($results);
                return current($results);
            case 0:
                return false;
            default:
                throw new Exception('Error 01: Multiple resource groups found');
        }
    }

    /**
     * @param $name
     * @return modResourceGroup new resource group
     */
    private function createPremiumGroup($name)
    {
        $resourceGroup = $this->modx->newObject('modResourceGroup');
        $resourceGroup->set('name', $name);
        $resourceGroup->save();

        $this->createPermissions($resourceGroup);

        return $resourceGroup;
    }

    /**
     * @param modResourceGroup $resourceGroup
     */
    private function createPermissions($resourceGroup)
    {
        $name = $resourceGroup->get('name');

        //user group
        $userGroup = $this->modx->newObject('modUserGroup');
        $userGroup->set('name', $name);
        $userGroup->save();

        //grant load privileges to new user group to context
        $context = $this->resource->getOne('Context');
        $contextKey = $context->get('key');
        $this->grantContextLoad($userGroup, $contextKey);

        //resource group access (add admins and managers as well)
        $this->grantResourceGroupAccess($userGroup, $resourceGroup, $contextKey);

        $this->flushPermissions();
    }

    /**
     * @param modUserGroup $userGroup to grant load access
     * @param string $key for context
     */
    private function grantContextLoad($userGroup, $key)
    {
        /**
         * @var xPDOQuery $policyQuery
         * @var modAccessContext $contextAccess
         * @var modAccessPolicy $accessPolicy
         */
        $policyQuery = $this->modx->newQuery('modAccessPolicy');
        $policyQuery->where(array(
            'name' => 'Load Only'
        ));

        $accessPolicy = $this->modx->getObject('modAccessPolicy', $policyQuery);

        $contextAccess = $this->modx->newObject('modAccessContext');
        $contextAccess->set('target', $key);
        $contextAccess->set('principal_class', 'modUserGroup');
        $contextAccess->set('principal', $userGroup->get('id'));
        $contextAccess->set('policy', $accessPolicy->get('id'));
        $contextAccess->set('authority', 9999);
        $contextAccess->save();
    }

    /**
     * @param modUserGroup $userGroup
     * @param modResourceGroup $resourceGroup
     * @param string $contextKey
     */
    private function grantResourceGroupAccess($userGroup, $resourceGroup, $contextKey)
    {
        /**
         * @var modAccessPolicy $adminPolicy
         * @var modAccessPolicy $loadPolicy
         * @var modAccessPolicy $viewPolicy
         * @var modAccessResourceGroup $resourceGroupAccess
         */

        $adminGroups = $this->modx->getOption('premiumpages.admins');
        $adminGroups = explode(',', $adminGroups);


        $adminPolicy = $this->modx->getObject('modAccessPolicy', array('name' => 'Resource'));
        $viewPolicy = $this->modx->getObject('modAccessPolicy', array('name' => 'Load, List and View'));

        //we only need the ID's really
        $adminPolicyId = $adminPolicy->get('id');
        $viewPolicyId = $viewPolicy->get('id');

        //add admins
        foreach ($adminGroups as $adminGroup) {
            $adminGroupId = intval($adminGroup);
            $resourceGroupAccess = $this->modx->newObject('modAccessResourceGroup');
            $resourceGroupAccess->set('target', $resourceGroup->get('id'));
            $resourceGroupAccess->set('principal_class', 'modUserGroup');
            $resourceGroupAccess->set('principal', $adminGroupId);
            $resourceGroupAccess->set('authority', 9999);
            $resourceGroupAccess->set('policy', $adminPolicyId);
            $resourceGroupAccess->set('context_key', $contextKey);
            $resourceGroupAccess->save();
        }

        //add new user group
        $resourceGroupAccess = $this->modx->newObject('modAccessResourceGroup');
        $resourceGroupAccess->set('target', $resourceGroup->get('id'));
        $resourceGroupAccess->set('principal_class', 'modUserGroup');
        $resourceGroupAccess->set('principal', $userGroup->get('id'));
        $resourceGroupAccess->set('authority', 9999);
        $resourceGroupAccess->set('policy', $viewPolicyId);
        $resourceGroupAccess->set('context_key', $contextKey);
        $resourceGroupAccess->save();


    }

    private function flushPermissions()
    {

        $this->modx->runProcessor('security/access/flush');
        //alternate permission flush from gist
        //$modx->user->getAttributes(array(), '', true);
    }

    /**
     * @param int|null $product pass ID of page
     * @return string The prefix for Resource Groups etc...
     */
    private function getPrefix($product = null)
    {
        $id = intval($product);
        if(empty($product)) {
            $id = $this->resource->get('id');
        }
        return sprintf($this->resourceGroupPrefix, $id);
    }

    /**
     * @param modResource $resource
     */
    public function setResource($resource)
    {
        $this->resource = $resource;
    }

    /**
     * @param modResource $resource
     * @return bool isPremiumContent
     */
    private function isPremiumPage($resource)
    {
        $thisTemplate = $resource->get('template');
        $templates = $this->modx->getOption('premiumpages.templates');
        $templates = explode(',', $templates);
        return in_array($thisTemplate, $templates);
    }
}