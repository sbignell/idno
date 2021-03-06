<?php

    /**
     * Syndication (or POSSE - Publish Own Site, Share Everywhere) helpers
     *
     * @package idno
     * @subpackage core
     */

    namespace Idno\Core {

        class Syndication extends \Idno\Common\Component
        {

            public $services = array();
            public $accounts = array();
            public $checkers = array(); // Our array of "does user X have service Y enabled?" checkers

            function init()
            {
            }

            function registerEventHooks()
            {
                \Idno\Core\site()->events()->addListener('syndicate', function (\Idno\Core\Event $event) {

                    $eventdata = $event->data();
                    if (!empty($eventdata['object'])) {
                        $content_type = $eventdata['object']->getActivityStreamsObjectType();
                        if ($services = \Idno\Core\site()->syndication()->getServices($content_type)) {
                            if ($selected_services = \Idno\Core\site()->currentPage()->getInput('syndication')) {
                                if (!empty($selected_services) && is_array($selected_services)) {
                                    foreach ($selected_services as $selected_service) {
                                        $event->data()['syndication_account'] = false;
                                        if (in_array($selected_service, $services)) {
                                            \Idno\Core\site()->events()->dispatch('post/' . $content_type . '/' . $selected_service, $event);
                                        }
                                        if ($implied_service = $this->getServiceByAccountString($selected_service)) {
                                            $event->data()['syndication_account'] = $this->getAccountFromAccountString($selected_service);
                                            \Idno\Core\site()->events()->dispatch('post/' . $content_type . '/' . $implied_service, $event);
                                        }
                                    }
                                }
                            }
                        }
                    }

                });
            }

            /**
             * Register syndication $service with idno.
             * @param string $service The name of the service.
             * @param callable $checker A function that will return true if the current user has the service enabled; false otherwise
             * @param array $content_types An array of content types that the service supports syndication for
             */
            function registerService($service, callable $checker, $content_types = array('article', 'note', 'event', 'rsvp', 'reply'))
            {
                $service = strtolower($service);
                if (!empty($content_types)) {
                    foreach ($content_types as $content_type) {
                        $this->services[$content_type][] = $service;
                    }
                }
                $this->checkers[$service] = $checker;
                \Idno\Core\site()->template()->extendTemplate('content/syndication', 'content/syndication/' . $service);
            }

            /**
             * Registers an account on a particular service as being available. The service itself must also have been registered.
             * @param string $service The name of the service.
             * @param string $username The username or user identifier on the service.
             * @param $display_name A human-readable name for this account.
             */
            function registerServiceAccount($service, $username, $display_name)
            {
                $service = strtolower($service);
                if (!empty($this->accounts[$service])) {
                    foreach($this->accounts[$service] as $key => $account) {
                        if ($account['username'] == $username) {
                            unset($this->accounts[$service][$key]); // Remove existing entry if it exists, so fresher one can be added
                        }
                    }
                }
                $this->accounts[$service][] = array('username' => $username, 'name' => $display_name);
            }

            /**
             * Adds a content type that the specified service will support
             * @param $service
             * @param $content_type
             */
            function addServiceContentType($service, $content_type)
            {
                if (!empty($this->services[$content_type]) && !in_array($service, $this->services[$content_type])) {
                    $this->services[$content_type][] = $service;
                }
            }

            /**
             * Return an array of the services registered for a particular content type
             * @param $content_type
             * @return array
             */
            function getServices($content_type = false)
            {
                if (!empty($content_type)) {
                    if (!empty($this->services[$content_type])) {
                        return $this->services[$content_type];
                    }
                } else {
                    $return = array();
                    if (!empty($this->services)) {
                        foreach($this->services as $service) {
                            $return = array_merge($return, $service);
                        }
                    }
                    return array_unique($return);
                }

                return array();
            }

            /**
             * Retrieve the user identifiers associated with syndicating to the specified service
             * @param $service
             * @return bool
             */
            function getServiceAccounts($service)
            {
                if (!empty($this->accounts[$service])) {
                    return $this->accounts[$service];
                }
                return false;
            }

            /**
             * Retrieve all the account identifiers associated with syndicating to all registered services
             * @return array
             */
            function getServiceAccountsByService()
            {
                if (!empty($this->accounts)) {
                    return $this->accounts;
                }
                return array();
            }

            /**
             * Given an account string (generated by the syndication input buttons), returns the service it's associated with
             * @param $account_string
             * @return bool|int|string
             */
            function getServiceByAccountString($account_string) {
                if ($accounts = $this->getServiceAccountsByService()) {
                    foreach($accounts as $service => $account_list) {
                        foreach($account_list as $listed_account) {
                            if ($account_string == $service . '::' . $listed_account['username']) {
                                return $service;
                            }
                        }
                    }
                }
                return false;
            }

            /**
             * Given an account string (generated by the syndication input buttons), returns the account portion
             * @param $account_string
             * @return bool|mixed
             */
            function getAccountFromAccountString($account_string) {
                if ($service = $this->getServiceByAccountString($account_string)) {
                    return str_replace($service . '::', '', $account_string);
                }
                return false;
            }

            //function triggerSyndication

            /**
             * Does the currently logged-in user have service $service?
             * @param $service
             * @return bool
             */
            function has($service)
            {
                if (!array_key_exists($service, $this->checkers)) {
                    return false;
                }
                $checker = $this->checkers[$service];

                return $checker();
            }

        }

    }
