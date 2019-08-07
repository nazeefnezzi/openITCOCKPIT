<?php
// Copyright (C) <2015>  <it-novum GmbH>
//
// This file is licensed under the terms of the openITCOCKPIT Enterprise Edition license agreement.
// The license agreement and license key were sent with the order confirmation.
use App\Model\Table\AgentchecksTable;
use App\Model\Table\ServicetemplatesTable;
use Cake\ORM\TableRegistry;
use itnovum\openITCOCKPIT\Core\AngularJS\Api;
use itnovum\openITCOCKPIT\Database\PaginateOMat;
use itnovum\openITCOCKPIT\Filter\AgentchecksFilter;

/**
 * Class AgentchecksController
 * @property AppPaginatorComponent $Paginator
 */
class AgentchecksController extends AppController {
    public $layout = 'blank';

    public function index() {
        if (!$this->isAngularJsRequest()) {
            //Only ship template
            return;
        }

        /** @var $AgentchecksTable AgentchecksTable */
        $AgentchecksTable = TableRegistry::getTableLocator()->get('Agentchecks');

        $AgentchecksFilter = new AgentchecksFilter($this->request);
        $PaginateOMat = new PaginateOMat($this->Paginator, $this, $this->isScrollRequest(), $AgentchecksFilter->getPage());

        $MY_RIGHTS = $this->MY_RIGHTS;
        if ($this->hasRootPrivileges) {
            $MY_RIGHTS = [];
        }
        $agentchecks = $AgentchecksTable->getAgentchecksIndex($AgentchecksFilter, $PaginateOMat, $MY_RIGHTS);


        $all_agentchecks = [];
        foreach ($agentchecks as $index => $agentcheck) {
            /** @var \App\Model\Entity\Agentcheck $agentcheck */
            $all_agentchecks[$index] = $agentcheck->toArray();
            $all_agentchecks[$index]['allow_edit'] = true;
            if ($this->hasRootPrivileges === false) {
                $all_agentchecks[$index]['allow_edit'] = $this->isWritableContainer($agentcheck->get('servicetemplate')->get('container_id'));
            }
        }


        $this->set('all_agentchecks', $all_agentchecks);
        $toJson = ['all_agentchecks', 'paging'];
        if ($this->isScrollRequest()) {
            $toJson = ['all_agentchecks', 'scroll'];
        }
        $this->set('_serialize', $toJson);

    }

    public function add() {
        if (!$this->isApiRequest()) {
            //Only ship HTML template for angular
            return;
        }

        if ($this->request->is('post')) {
            /** @var $AgentchecksTable AgentchecksTable */
            $AgentchecksTable = TableRegistry::getTableLocator()->get('Agentchecks');
            $agentcheck = $AgentchecksTable->newEntity();
            $agentcheck = $AgentchecksTable->patchEntity($agentcheck, $this->request->data('Agentcheck'));

            $AgentchecksTable->save($agentcheck);
            if ($agentcheck->hasErrors()) {
                $this->response->statusCode(400);
                $this->set('error', $agentcheck->getErrors());
                $this->set('_serialize', ['error']);
                return;
            } else {
                if ($this->request->ext == 'json') {
                    $this->serializeCake4Id($agentcheck); // REST API ID serialization
                    return;
                }
            }
            $this->set('agentcheck', $agentcheck);
            $this->set('_serialize', ['agentcheck']);
        }
    }

    /**
     * @param int|null $id
     */
    public function edit($id = null) {
        if (!$this->isApiRequest()) {
            //Only ship HTML template for angular
            return;
        }

        /** @var $AgentchecksTable AgentchecksTable */
        $AgentchecksTable = TableRegistry::getTableLocator()->get('Agentchecks');

        if (!$AgentchecksTable->existsById($id)) {
            throw new NotFoundException(__('Agentcheck not found'));
        }

        $agentcheck = $AgentchecksTable->getAgentcheckById($id);

        $allowEdit = $this->isWritableContainer($agentcheck->get('servicetemplate')->get('container_id'));
        if (!$allowEdit) {
            $this->render403();
            return;
        }

        if ($this->request->is('post')) {
            $agentcheck = $AgentchecksTable->patchEntity($agentcheck, $this->request->data('Agentcheck'));

            $AgentchecksTable->save($agentcheck);
            if ($agentcheck->hasErrors()) {
                $this->response->statusCode(400);
                $this->set('error', $agentcheck->getErrors());
                $this->set('_serialize', ['error']);
                return;
            } else {
                if ($this->request->ext == 'json') {
                    $this->serializeCake4Id($agentcheck); // REST API ID serialization
                    return;
                }
            }
        }
        $this->set('agentcheck', $agentcheck);
        $this->set('_serialize', ['agentcheck']);
    }

    public function delete($id) {
        if (!$this->request->is('post')) {
            throw new MethodNotAllowedException();
        }

        /** @var $AgentchecksTable AgentchecksTable */
        $AgentchecksTable = TableRegistry::getTableLocator()->get('Agentchecks');

        if (!$AgentchecksTable->existsById($id)) {
            throw new NotFoundException(__('Agentcheck not found'));
        }

        $agentcheck = $AgentchecksTable->getAgentcheckById($id);

        $allowEdit = $this->isWritableContainer($agentcheck->get('servicetemplate')->get('container_id'));
        if (!$allowEdit) {
            $this->render403();
            return;
        }

        if ($AgentchecksTable->delete($agentcheck)) {
            $this->set('success', true);
            $this->set('_serialize', ['success']);
            return;
        }

        $this->response->statusCode(500);
        $this->set('success', false);
        $this->set('_serialize', ['success']);
    }

    public function loadServicetemplates() {
        /** @var $ServicetemplatesTable ServicetemplatesTable */
        $ServicetemplatesTable = TableRegistry::getTableLocator()->get('Servicetemplates');

        $servicetemplates = $ServicetemplatesTable->getServicetemplatesByContainerId($this->MY_RIGHTS, 'list', OITC_AGENT_SERVICE);
        $servicetemplates = Api::makeItJavaScriptAble($servicetemplates);

        $this->set('servicetemplates', $servicetemplates);
        $this->set('_serialize', ['servicetemplates']);
    }

}