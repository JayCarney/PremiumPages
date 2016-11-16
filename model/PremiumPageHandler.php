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
     */

    private $modx;
    private $resourceGroupPrefix = 'Premium Page %d ';
    private $resource;

    /**
     * @param modX $modx
     * @param modResource $resource
     */
    public function __construct($modx, $resource = null)
    {
        $this->modx = $modx;
        $this->resource = $resource;
    }

    /**
     * @return boolean false if not premium, true on success
     */
    public function handlePageSave()
    {

        if(!$this->isPremiumPage($this->resource)){
            return false; //nothing to do
        }

        //attach to relevant resource group
        $this->assignResourceGroup();

        return true;
    }

    /**
     * @param string $stripeToken stripe payment token
     * @param string $priceTv name of tv holding the price to charge
     * @param string $pagesSoldTv name of tv holding ID's of pages to grant access to
     * @param int $salesPage ID of pages with pagesSoldTv value we need to inspect
     */
    public function handlePayment($stripeToken, $priceTv, $pagesSoldTv, $salesPage)
    {
        //check requested purchase is valid.
        $userGroups = $this->getRequestedUserGroups($pagesSoldTv, $salesPage);
        //process payment

        //add user to group

        //return success
    }

    /**
     * @param string $pagesSoldTv
     * @param string $salesPage
     * @return modUserGroup[]
     * @throws Exception 03: page not found
     * @throws Exception 04: No products
     */
    private function getRequestedUserGroups($pagesSoldTv, $salesPage)
    {
        /**
         * @var modResource $salesResource
         */
        //grab our sales page
        $salesResource = $this->modx->getObject('modResource', intval($salesPage));
        if(!$salesResource instanceof modResource){
            throw new Exception('Error 03: Sales page not found (ID: '.$salesPage.')');
        }

        //grab which products we're buying
        $products = $salesResource->getTVValue($pagesSoldTv);
        $products = explode(',',$products);

        if(count($products) < 1){
            throw new Exception('Error 04: No Product\'s associated with given sales page (ID: '.$salesPage.')');
        }

        foreach ($products as &$product){ //replace id with actual page

        }
    }

    /**
     * @throws Exception 02: No resource group
     */
    private function assignResourceGroup()
    {

        $resourceGroup = $this->getResourceGroup();
        if($resourceGroup instanceof modResourceGroup){
            $resourceGroupName = $resourceGroup->get('name');
            if(!$this->resource->isMember($resourceGroupName)){
                $this->resource->joinGroup($resourceGroup);
            }
        } else {
            throw new Exception('Error 02: No valid resource group');
        }
    }

    /**
     * @description Will return an existing resource group, or create a new one
     *
     * @return modResourceGroup
     * @var xPDOQuery $query;
     * @throws Exception 01: too many results
     */
    private function getResourceGroup()
    {
        $prefix = $this->getPrefix();
        $query = $this->modx->newQuery('modResourceGroup');
        $query->where(array(
            'name:LIKE' => $prefix.'%'
        ));

        $results = $this->modx->getCollection('modResourceGroup', $query);

        switch (count($results)) {
            case 1:
                reset($results);
                return current($results);
            case 0:
                $name = $prefix . $this->resource->get('pagetitle');
                return $this->createPremiumGroup($name);
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
        $userGroup->set('name',$name);
        $userGroup->save();

        //grant load privileges to new user group to context
        $context = $this->resource->getOne('Context');
        $contextKey = $context->get('key');
        $this->grantContextLoad($userGroup, $contextKey);

        //resource group access (add admins and managers as well)
        $this->grantResourceGroupAccess($userGroup,$resourceGroup,$contextKey);

        $this->flushPermissions();
    }

    /**
     * @param modUserGroup $userGroup to grant load access
     * @param string $key for context
     */
    private function grantContextLoad($userGroup,$key)
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
        $contextAccess->set('authority',9999);
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
         * @var modAccessResourceGroup $resourceGroupAccess
         */

        $adminGroups = $this->modx->getOption('premiumpages.admins');
        $adminGroups = explode(',', $adminGroups);


        $adminPolicy = $this->modx->getObject('modAccessPolicy', array('name' => 'Resource'));
        $loadPolicy = $this->modx->getObject('modAccessPolicy', array('name' => 'Load Only'));
        //we only need the ID's really
        $adminPolicyId = $adminPolicy->get('id');
        $loadPolicyId = $loadPolicy->get('id');

        //add admins
        foreach ($adminGroups as $adminGroup){
            $adminGroupId = intval($adminGroup);
            $resourceGroupAccess = $this->modx->newObject('modAccessResourceGroup');
            $resourceGroupAccess->set('target', $resourceGroup->get('id'));
            $resourceGroupAccess->set('principal_class', 'modUserGroup');
            $resourceGroupAccess->set('principal', $adminGroupId);
            $resourceGroupAccess->set('authority',9999);
            $resourceGroupAccess->set('policy', $adminPolicyId);
            $resourceGroupAccess->set('context_key', $contextKey);
            $resourceGroupAccess->save();
        }

        //add new user group

        $resourceGroupAccess = $this->modx->newObject('modAccessResourceGroup');
        $resourceGroupAccess->set('target', $resourceGroup->get('id'));
        $resourceGroupAccess->set('principal_class', 'modUserGroup');
        $resourceGroupAccess->set('principal', $userGroup->get('id'));
        $resourceGroupAccess->set('authority',9999);
        $resourceGroupAccess->set('policy',$loadPolicyId);
        $resourceGroupAccess->set('context_key',$contextKey);
        $resourceGroupAccess->save();


    }

    private function flushPermissions()
    {
        $this->modx->runProcessor('security/access',array(
            'action' => 'flush'
        ));
        //alternate permission flush from gist
        //$modx->user->getAttributes(array(), '', true);
    }

    /**
     * @return string The prefix for Resource Groups etc...
     */
    private function getPrefix()
    {
        $id = $this->resource->get('id');
        return sprintf($this->resourceGroupPrefix,$id);
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
        $templates = explode(',',$templates);
        return in_array($thisTemplate, $templates);
    }
}