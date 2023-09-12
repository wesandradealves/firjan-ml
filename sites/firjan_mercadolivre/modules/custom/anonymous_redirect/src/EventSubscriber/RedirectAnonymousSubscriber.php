<?php
     
     namespace Drupal\anonymous_redirect\EventSubscriber;
      
     use Symfony\Component\EventDispatcher\EventSubscriberInterface;
     use Symfony\Component\HttpFoundation\RedirectResponse;
     use Symfony\Component\HttpKernel\Event\GetResponseEvent;
     use Symfony\Component\HttpKernel\KernelEvents;
     
     /**
      * Event subscriber subscribing to KernelEvents::REQUEST.
      */
     class RedirectAnonymousSubscriber implements EventSubscriberInterface {
       public function checkAuthStatus(GetResponseEvent $event) {
        global $base_url;
        $is_front = \Drupal::service('path.matcher')->isFrontPage();
        $roles = \Drupal::currentUser()->getRoles();
        $route_name = \Drupal::routeMatch()->getRouteName();    

        if(!\Drupal::currentUser()->isAnonymous()) {
            if($is_front) {
                $response = new RedirectResponse(!in_array('administrator', $roles) ? $base_url.'/dashboard' : $base_url.'/admin', 301);
                $event->setResponse($response);
                $event->stopPropagation();
                return;
            }
        }
      
        //  if (
        //    \Drupal::currentUser()->isAnonymous() &&
        //    \Drupal::routeMatch()->getRouteName() != 'user.login' &&
        //    \Drupal::routeMatch()->getRouteName() != 'user.reset' &&
        //    \Drupal::routeMatch()->getRouteName() != 'user.reset.form' &&
        //    \Drupal::routeMatch()->getRouteName() != 'user.reset.login' &&
        //    \Drupal::routeMatch()->getRouteName() != 'user.pass' ) {
        //    // add logic to check other routes you want available to anonymous users,
        //    // otherwise, redirect to login page.
        //    $route_name = \Drupal::routeMatch()->getRouteName();       

        //    if (strpos($route_name, 'view') === 0 && strpos($route_name, 'rest_') !== FALSE) {
        //      return;
        //    }

        //    $array = null;
        //    $is404 = false;
        //    $is_front = \Drupal::service('path.matcher')->isFrontPage();

        //    if(strpos($route_name, 'system') !== false) {
        //         $array = explode(".", $route_name);
        //         $is404 = $array[1] == '404';
        //    }
           
        //    if(!$is_front && !$is404) {
        //     $response = new RedirectResponse($base_url, 301);
        //     $event->setResponse($response);
        //    }

        //    $event->stopPropagation();
        //    return;
        //  } 
         
        //  else {
        //    // add logic to check other routes you want available to anonymous users,
        //    // otherwise, redirect to login page.
        //    $route_name = \Drupal::routeMatch()->getRouteName();       

        //    if (strpos($route_name, 'view') === 0 && strpos($route_name, 'rest_') !== FALSE) {
        //      return;
        //    }

        //    $array = null;
        //    $is404 = false;
        //    $is_front = \Drupal::service('path.matcher')->isFrontPage();
        //    $roles = \Drupal::currentUser()->getRoles();

        //    if(strpos($route_name, 'system') !== false) {
        //         $array = explode(".", $route_name);
        //         $is404 = $array[1] == '404';
        //    }
           
        //    if($is_front) {
        //     $response = new RedirectResponse(!in_array('administrator', $roles) ? $base_url.'/dashboard' : $base_url.'/admin/', 301);
        //     $event->setResponse($response);
        //    }

        //    $event->stopPropagation();
        //    return;
        //  }
       }
       public static function getSubscribedEvents() {
         $events[KernelEvents::REQUEST][] = array('checkAuthStatus');
         return $events;
       }
     }