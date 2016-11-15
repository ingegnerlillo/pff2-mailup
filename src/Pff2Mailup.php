<?php

namespace pff\modules;


use Doctrine\ORM\EntityManager;
use pff\Abs\AModel;
use pff\Abs\AModule;
use pff\Core\ServiceContainer;
use pff\Exception\PffException;
use pff\Iface\IConfigurableModule;

/**
 * Class Pff2Mailup
 * @package pff\modules
 */
class Pff2Mailup extends AModule implements IConfigurableModule
{

    private $clientId;
    private $clientSecret;
    private $username;
    private $password;
    private $client;
    private $emailField;
    private $nameField;
    private $remoteIdField;
    private $contactFields;

    public function __construct($confFile = 'pff2-mailup/module.conf.local.yaml')
    {
        $this->loadConfig($confFile);
        $this->client = new MailUpClient($this->clientId, $this->clientSecret, "");
    }

    /**
     * @param array $parsedConfig
     * @return mixed
     */
    public function loadConfig($parsedConfig)
    {
        $conf = $this->readConfig($parsedConfig);
        $this->clientId = $conf['moduleConf']['clientId'];
        $this->clientSecret = $conf['moduleConf']['clientSecret'];
        $this->username = $conf['moduleConf']['username'];
        $this->password = $conf['moduleConf']['password'];
        $this->emailField = $conf['moduleConf']['emailField'];
        $this->nameField = $conf['moduleConf']['nameField'];
        $this->remoteIdField = $conf['moduleConf']['remoteIdField'];
        $this->contactFields = $conf['moduleConf']['contactFields'];
    }

    /**
     * @return mixed
     * @throws \Exception
     *
     * CHECK LOGIN TO MAILUP
     */
    public function checkLogin(){
        if(!$this->client->checkToken()){
            $token = $this->client->retreiveAccessToken($this->username, $this->password);
        }else{
            $token = $this->client->getAccessToken();
        }
        return $token;
    }

    /**
     * @return mixed
     *
     * GETS AN ARRAY OF LISTS
     */
    public function getLists(){
        $this->checkLogin();
        $status = $this->client->getLists();
        return $status;
    }

    /**
     * @param $idList
     * @return mixed
     *
     * GETS AN ARRAY OF GROUPS
     */
    public function getGroups($idList){
        $this->checkLogin();
        $status = $this->client->getGroups($idList);
        return $status;
    }

    /**
     * @param AModel $user
     * @param $idList
     * @return int
     * @throws \Exception
     *
     * SUBSCRIBE AN USER TO A LIST
     */
    public function subscribeToList(AModel $user, $idList){
        $this->checkLogin();
        $request = $this->getRequestUserData($user);
        try{
            $status = $this->client->subscribeToList($request, $idList);
        }catch (\Exception $e){
            throw $e;
        }
        /** @var EntityManager $em */
        $em = ServiceContainer::get("dm");
        $this->setField($user, $this->remoteIdField, (int)trim($status));
        $em->flush();
        $em->clear();
        return $status;
    }

    /**
     * @param AModel $user
     * @param $idGroup
     * @return mixed
     * @throws \Exception
     *
     * SUBSCRIBE AN USER TO A GROUP, AND TO THE LIST IT BELONGS TO
     */
    public function subscribeToGroup(AModel $user, $idGroup){
        $this->checkLogin();
        $request = $this->getRequestUserData($user);
        try{
            $status = $this->client->subscribeToGroup($request, $idGroup);
        }catch(\Exception $e){
            throw $e;
        }
        /** @var EntityManager $em */
        $em = ServiceContainer::get("dm");
        $this->setField($user, $this->remoteIdField, (int)trim($status));
        $em->flush();
        $em->clear();
        return $status;
    }

    /**
     * @param AModel $user
     * @param $idGroup
     * @return mixed
     *
     * UNSUBSCRIBE AN USER FROM A GROUP, BUT NOT FROM THE LIST
     */
    public function unsubscribeFromGroup(AModel $user, $idGroup){
        $this->checkLogin();
        $idRemote = $this->getField($user, $this->remoteIdField);
        $status = $this->client->unsubscribeFromGroup($idRemote, $idGroup);
        return $status;
    }

    /**
     * @param AModel $user
     * @param $idList
     * @return mixed
     *
     * UNSUBSCRIBE AN USER FROM A LIST
     */
    public function unsubscribeFromList(AModel $user, $idList){
        $this->checkLogin();
        $idRemote = $this->getField($user, $this->remoteIdField);
        $status = $this->client->unsubscribeFromList($idRemote, $idList);
        return $status;
    }

    public function getClient(){
        return $this->client;
    }

    /**
     * @param $idGroup
     * @param $users
     * @return mixed
     *
     * EXPORT A BULK OF DATA TO A GROUP
     */
    public function doBulkGroupExport($idGroup, $users){
        $this->checkLogin();
        $toExport = array();
        foreach($users as $u){
            $tmp = $this->getRequestUserData($u, false);
            array_push($toExport, $tmp);
        }
        $status = $this->client->doBulkGroupExport(json_encode($toExport), $idGroup);
        return $status;
    }

    /** RETURN A JSON WITH USER FIELDS FOR CONTACT UPDATE  */
    private function getRequestUserData($user, $json = true){
        $request = array();
        $request['Email'] = $this->getField($user, $this->emailField);
        $request['Name'] = $this->getField($user, $this->nameField);
        $request['Fields'] = array();
        foreach($this->contactFields as $field){
            $tmp = array();
            $tmp['Description'] = $field['name'];
            $tmp['Id'] = $field['id'];
            $tmp['Value'] = $this->getField($user, $field['name']);
            array_push($request['Fields'],$tmp);
        }
        $request = $json ? json_encode($request) : $request;
        return $request;
    }

    private function getField(AModel $user, $field){
        $toReturn = call_user_func(array($user,"get".ucfirst($field)));
        return $toReturn ?: "-";
    }

    private function setField(AModel $user, $field, $value){
        return call_user_func_array(array($user, "set".ucfirst($field)), array($value));
    }
}
