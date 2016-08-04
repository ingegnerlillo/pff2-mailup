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

    private function checkLogin(){
        if(!$this->client->checkToken()){
            $token = $this->client->retreiveAccessToken($this->username, $this->password);
        }else{
            $token = $this->client->getAccessToken();
        }
        return $token;
    }

    public function subscribeToList(AModel $user, $idList = false){
        $this->checkLogin();
        $lists = $this->client->getLists();
        if(!$idList){
            $listId = $lists[0]->idList;
        }else{
            if(is_int($idList)){
                $listId = $idList;
            }else{
                foreach($lists as $l){
                    if($l->Name == $idList){
                        $listId = $l->idList;
                    }
                }
            }
        }
        $request = $this->getRequestUserData($user);
        $status = $this->client->subscribeToList($request, $listId);
        return $status;
    }

    public function subscribeToGroup(AModel $user, $idGroup){
        $this->checkLogin();
        $request = $this->getRequestUserData($user);
        $status = $this->client->subscribeToGroup($request, $idGroup);
        /** @var EntityManager $em */
        $em = ServiceContainer::get("dm");
        $this->setField($user, $this->remoteIdField, $status);
        $em->flush($user);
        return $status;
    }

    public function unsubscribeFromGroup(AModel $user, $idGroup){
        $this->checkLogin();
        $idRemote = $this->getField($user, $this->remoteIdField);
        $status = $this->client->unsubscribeFromGroup($idRemote, $idGroup);
        return $status;
    }

    public function unsubscribeFromList(AModel $user, $idList){
        $this->checkLogin();
        $idRemote = $this->getField($user, $this->remoteIdField);
        $status = $this->client->unsubscribeFromList($idRemote, $idList);
        return $status;
    }

    private function getRequestUserData($user){
        $request = array();
        $request['Email'] = $this->getField($user, $this->emailField);
        $request['Name'] = $this->getField($user, $this->nameField);
        $request['Fields'] = array();
        foreach($this->contactFields as $field){
            $request['Fields'][$field['name']] =  $this->getField($user, $field['name']);
        }
        $request = json_encode($request, JSON_FORCE_OBJECT);
        return $request;
    }

    private function getField(AModel $user, $field){
        return call_user_func(array($user,"get".ucfirst($field)));
    }

    private function setField(AModel $user, $field, $value){
        return call_user_func_array(array($user, "set".ucfirst($field)), array($value));
    }
}