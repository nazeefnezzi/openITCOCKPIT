<?php
// Copyright (C) <2015>  <it-novum GmbH>
//
// This file is dual licensed
//
// 1.
//	This program is free software: you can redistribute it and/or modify
//	it under the terms of the GNU General Public License as published by
//	the Free Software Foundation, version 3 of the License.
//
//	This program is distributed in the hope that it will be useful,
//	but WITHOUT ANY WARRANTY; without even the implied warranty of
//	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//	GNU General Public License for more details.
//
//	You should have received a copy of the GNU General Public License
//	along with this program.  If not, see <http://www.gnu.org/licenses/>.
//

// 2.
//	If you purchased an openITCOCKPIT Enterprise Edition you can use this file
//	under the terms of the openITCOCKPIT Enterprise Edition license agreement.
//	License agreement and license key will be shipped with the order
//	confirmation.
?>
<ol class="breadcrumb page-breadcrumb">
    <li class="breadcrumb-item">
        <a href="<?php echo $this->webroot; ?>">
            <i class="fa fa-home"></i> <?php echo __('Home'); ?>
        </a>
    </li>
    <li class="breadcrumb-item">
        <a ui-sref="ContactsIndex">
            <i class="fa fa-user"></i> <?php echo __('Contacts'); ?>
        </a>
    </li>
    <li class="breadcrumb-item">
        <a ui-sref="ContactsCopy">
            <i class="fa fa-copy"></i> <?php echo __('Copy'); ?>
        </a>
    </li>
</ol>
<div class="row">
    <div class="col-xl-12">
        <div id="panel-1" class="panel">
            <div class="panel-hdr">
                <h2>
                    <?php echo __('Contacts'); ?>
                    <span class="fw-300"><i><?php echo __('Copy contact/s'); ?></i></span>
                </h2>
                <div class="panel-toolbar">
                    <?php if ($this->Acl->hasPermission('index', 'contacts')): ?>
                        <a back-button fallback-state='ContactsIndex' class="btn btn-default btn-xs mr-1 shadow-0">
                            <i class="glyphicon glyphicon-white glyphicon-arrow-left"></i> <?php echo __('Back to list'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="panel-container show">
                <div class="panel-content">
                    <div class="card margin-bottom-10" ng-repeat="sourceContact in sourceContacts">
                        <div class="card-header">
                            <i class="fa fa-cog"></i>
                            <?php echo __('Source contact:'); ?>
                            {{sourceContact.Source.name}}
                        </div>
                        <div class="card-body">
                            <div class="form-group required" ng-class="{'has-error': sourceContact.Error.name}">
                                <label for="Contact{{$index}}Name" class="control-label">
                                    <?php echo('Contact name'); ?>
                                </label>
                                <input
                                        class="form-control"
                                        type="text"
                                        ng-model="sourceContact.Contact.name"
                                        id="Contact{{$index}}Name">
                                <span class="help-block">
                                <?php echo __('Name of the new contact'); ?>
                                </span>
                                <div ng-repeat="error in sourceContact.Error.name">
                                    <div class="help-block text-danger">{{ error }}</div>
                                </div>
                            </div>
                            <div class="form-group" ng-class="{'has-error': sourceContact.Error.description}">
                                <label for="Contact{{$index}}Description" class="control-label">
                                    <?php echo('Description'); ?>
                                </label>
                                <input
                                        class="form-control"
                                        type="text"
                                        ng-model="sourceContact.Contact.description"
                                        id="Contact{{$index}}Description">
                                <div ng-repeat="error in sourceContact.Error.description">
                                    <div class="help-block text-danger">{{ error }}</div>
                                </div>
                            </div>

                            <div class="form-group required" ng-class="{'has-error': sourceContact.Error.email}">
                                <label for="Contact{{$index}}Email" class="control-label">
                                    <?php echo('Email'); ?>
                                </label>
                                <input
                                        class="form-control"
                                        type="text"
                                        ng-model="sourceContact.Contact.email"
                                        id="Contact{{$index}}Email">
                                <div ng-repeat="error in sourceContact.Error.email">
                                    <div class="help-block text-danger">{{ error }}</div>
                                </div>
                            </div>

                            <div class="form-group required" ng-class="{'has-error': sourceContact.Error.phone}">
                                <label for="Contact{{$index}}Phone" class="control-label">
                                    <?php echo('Phone'); ?>
                                </label>
                                <input
                                        class="form-control"
                                        type="text"
                                        ng-model="sourceContact.Contact.phone"
                                        id="Contact{{$index}}Phone">
                                <div ng-repeat="error in sourceContact.Error.phone">
                                    <div class="help-block text-danger">{{ error }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card margin-top-10">
                        <div class="card-body">
                            <div class="float-right">
                            <button class="btn btn-primary" ng-click="copy()">
                                <?php echo __('Copy'); ?>
                            </button>
                            <?php if ($this->Acl->hasPermission('index', 'Contacts')): ?>
                                <a back-button fallback-state='ContactsIndex'
                                   class="btn btn-default"><?php echo __('Cancel'); ?></a>
                            <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>