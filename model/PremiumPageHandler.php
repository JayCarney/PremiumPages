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
     * @param string $description
     */

    private $modx;
    private $resourceGroupPrefix = 'Premium Page %d ';
    private $resource;
    private $charge;
    private $description;
    private $cmAPI;

    /**
     * @param modX $modx
     * @param modResource $resource
     */
    public function __construct($modx, $resource = null)
    {
        $this->modx = $modx;
        $this->resource = $resource;
    }

    private function loadModxAPI($context)
    {
        define('MODX_API_MODE', true);
        $modx = new modX();
        $modx->initialize($context);
        $modx->getService('error','error.modError', '', '');
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
//        $this->loadModxAPI($this->modx->context->get('key'));

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

        $this->addToCampaignMonitor($user, $userGroups);

        $paymentResponse->setUser($user);

        //flush permissions
        $this->flushPermissions();

        //return payment response
        return $paymentResponse;

    }

    /**
     * @param $user modUser
     * @param string $product_var
     * @param modUserGroup[] $userGroups
     * @throws Exception
     * @return bool
     */
    public function addToCampaignMonitor($user, $userGroups = [], $product_var = 'Purchases'){
        $cm_api = $this->getCmAPI();
        if(empty($cm_api)){
            return null;
        }
        foreach ($userGroups as $userGroup){
            $productName = $userGroup->get('name');
            $cm_api->add_custom_field_value(
                $product_var,
                $productName,
                'MultiSelectMany', true);
        }

        $profile = $user->getOne('Profile');
        if(!$profile){
            throw new Exception('Error 11: User profile not found');
        }

        $name = $profile->get('fullname');
        $email = $profile->get('email');

        // update campaign monitor subscription
        $cm_api->subscribe($name, $email);
        return true;
    }

    /**
     * @return CM_API|null
     */
    private function getCmAPI(){
        try {
            if(!empty($this->cmAPI)){
                return $this->cmAPI;
            }
            $formHandlerPath = $this->modx->getOption('formhandler.core_path', null, MODX_CORE_PATH.'components/formhandler/');
            $apiKey = $this->modx->getOption('premiumpages.cm_api_key');
            $listId = $this->modx->getOption('premiumpages.cm_list_id');
            if(!empty($formHandlerPath) && !empty($apiKey) && !empty($listId)){
                include_once $formHandlerPath .'vendor/autoload.php';
                $this->cmAPI = new CM_API($apiKey, $listId);
                return $this->cmAPI;
            } else {
                return null;
            }
        } catch(Exception $e){
            return null;
        }
    }

    private function getPaymentDetails($salesPage, $priceTv)
    {
        $salesPageResource = $this->modx->getObject('modResource', $salesPage);
        if(!$salesPageResource){
            throw new Exception('Error 07: Invalid sales page provided (ID: '.$salesPage.')');
        }
        $description = $salesPageResource->get('pagetitle');
        $description .= ' (ID:'.$salesPageResource->get('id').')';

        $saleValue = floatval($salesPageResource->getTVValue($priceTv));

        if(empty($saleValue) || $saleValue < 0){
            throw new Exception('Error 08: Invalid price supplied (ID:'.$salesPage.')');
        }

        //convert to cents
        $this->charge = intval($saleValue * 100);
        $this->description = $description;
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
                $options['description'] = $this->description;
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

        // create option inside campaign monitor field
        // init cm_api class, leverage process_custom_fields
        $cm_api = $this->getCmAPI();
        if(!empty($cm_api)){
            $cm_api->add_custom_field_value('Purchases',$resourceGroupName,'MultiSelectMany', true);
            $cm_api->process_custom_fields();
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
        $clientGroups = $this->modx->getOption('premiumpages.clients');

        $adminGroups = explode(',', $adminGroups);
        $clientGroups = explode(',', $clientGroups);


        $adminPolicy = $this->modx->getObject('modAccessPolicy', array('name' => 'Resource'));
        $viewPolicy = $this->modx->getObject('modAccessPolicy', array('name' => 'Load, List and View'));
        $loadPolicy = $this->modx->getObject('modAccessPolicy', array('name' => 'Load Only'));

        //we only need the ID's really
        $adminPolicyId = $adminPolicy->get('id');
        $viewPolicyId = $viewPolicy->get('id');
        $loadPolicyId = $loadPolicy->get('id');

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

        //add load only clients
        foreach ($clientGroups as $clientGroup) {
            $clientGroupId = intval($clientGroup);
            $resourceGroupAccess = $this->modx->newObject('modAccessResourceGroup');
            $resourceGroupAccess->set('target', $resourceGroup->get('id'));
            $resourceGroupAccess->set('principal_class', 'modUserGroup');
            $resourceGroupAccess->set('principal', $clientGroupId);
            $resourceGroupAccess->set('authority', 9999);
            $resourceGroupAccess->set('policy', $loadPolicyId);
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

//        $this->modx->runProcessor('security/access/flush');
        //alternate permission flush from gist

        $targets = array('modAccessResourceGroup');
        $contextKey = $this->modx->context->get('key');
        $this->modx->user->loadAttributes($targets, $contextKey, true);
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
