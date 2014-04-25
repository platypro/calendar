<?php
/**
 * Copyright (c) 2014 Georg Ehrke <oc.list@georgehrke.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
namespace OCA\Calendar\Controller;

use \OCP\AppFramework\IAppContainer;
use \OCP\IRequest;

use \OCP\AppFramework\Http\Http;

use \OCA\Calendar\Db\DoesNotExistException;
use \OCA\Calendar\BusinessLayer\BusinessLayerException;

use \OCA\Calendar\Db\Object;
use \OCA\Calendar\Db\ObjectCollection;
use \OCA\Calendar\Db\ObjectType;

use \OCA\Calendar\Db\Permissions;

use \OCA\Calendar\BusinessLayer\CalendarBusinessLayer;
use \OCA\Calendar\BusinessLayer\ObjectBusinessLayer;

use \OCA\Calendar\Http\Response;

use \OCA\Calendar\Http\Reader;
use \OCA\Calendar\Http\Serializer;
use \OCA\Calendar\Http\ReaderExpcetion;
use \OCA\Calendar\Http\SerializerException;

abstract class ObjectTypeController extends ObjectController {

	/**
	 * type of object this controller is handling
	 * @var \OCA\Calendar\Db\ObjectType::...
	 */
	protected $objectType;

	/**
	 * constructor
	 * @param IAppContainer $app interface to the app
	 * @param IRequest $request an instance of the request
	 * @param CalendarBusinessLayer $calendarBusinessLayer
	 * @param ObjectBusinessLayer $objectBusinessLayer
	 * @param integer $objectType: type of object, use \OCA\Calendar\Db\ObjectType::...
	 */
	public function __construct(IAppContainer $app, IRequest $request,
								CalendarBusinessLayer $calendarBusinessLayer,
								ObjectBusinessLayer $objectBusinessLayer,
								$type){

		parent::__construct($app, $request,
							$calendarBusinessLayer,
							$objectBusinessLayer);

		$this->objectType = $type;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index() {
		try {
			$userId = $this->api->getUserId();
			$calendarId = $this->params('calendarId');

			$nolimit = $this->params('nolimit', false);
			if ($nolimit) {
				$limit = $offset = null;
			} else {
				$limit = $this->params('limit', 25);
				$offset = $this->params('offset', 0);
			}

			$start = $this->params('start');
			$end = $this->params('end');

			$type = $this->objectType;

			$calendar = $this->calendarBusinessLayer->find($calendarId, $userId);
			if ($calendar->doesAllow(Permissions::READ) === false) {
				throw new ForbiddenExpcetion();
			}

			$objectCollection = $this->objectBusinessLayer->findAllByType($calendar, $type,
																		  $limit, $offset);

			$serializer = new Serializer(Serializer::ObjectCollection, $objectCollection, $this->accept());

			return new Response($serializer);
		} catch (BusinessLayerException $ex) {
			$this->app->log($ex->getMessage(), 'warn');
			return new Response(array('message' => $ex->getMessage()), $ex->getCode());
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function indexInPeriod() {
		try {
			$userId = $this->api->getUserId();
			$calendarId = $this->params('calendarId');

			$nolimit = $this->params('nolimit', false);
			if ($nolimit) {
				$limit = $offset = null;
			} else {
				$limit = $this->params('limit', 25);
				$offset = $this->params('offset', 0);
			}

			$start = $this->params('start', new DateTime(date('Y-m-01')));
			$end = $this->params('end', new DateTime(date('Y-m-t')));

			$type = $this->objectType;

			$calendar = $this->calendarBusinessLayer->find($calendarId, $userId);
			if (!$calendar->doesAllow(Permissions::READ)) {
				return new Response(null, HTTP::STATUS_FORBIDDEN);
			}

			$objectCollection = $this->objectBusinessLayer->findAllByTypeInPeriod($calendar, $type, $start, $end,
																				  $limit, $offset);

			$serializer = new Serializer(Serializer::ObjectCollection, $objectCollection, $this->accept());

			return new Response($serializer);
		} catch (BusinessLayerException $ex) {
			$this->app->log($ex->getMessage(), 'warn');
			return new Response(array('message' => $ex->getMessage()), $ex->getCode());
		} catch (SerializerException $ex) {

		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function show() {
		try {
			$userId = $this->api->getUserId();
			$calendarId = $this->params('calendarId');
			$objectURI = $this->getObjectId();

			$type = $this->objectType;

			$calendar = $this->calendarBusinessLayer->find($calendarId, $userId);
			if (!$calendar->doesAllow(Permissions::READ)) {
				return new Response(null, HTTP::STATUS_FORBIDDEN);
			}

			$object = $this->objectBusinessLayer->findByType($calendar, $objectURI, $type);

			$serializer = new Serializer(Serializer::Object, $object, $this->accept());

			return new Response($serializer);
		} catch (BusinessLayerException $ex) {
			$this->app->log($ex->getMessage(), 'warn');
			return new Response(array('message' => $ex->getMessage()), $ex->getCode());
		}
	}

	/**
	 * @brief get objectId of request
	 * TODO - find a better solution
	 * @return $string objectId
	 */
	private function getObjectId() {
		list($routeApp, $routeController, $routeMethod) = explode('.', $this->params('_route'));
		return $this->params($routeController . 'Id');
	}
}