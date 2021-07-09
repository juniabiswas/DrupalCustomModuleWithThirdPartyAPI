<?php

namespace Drupal\transport_details\Controller;

use Drupal\Core\Controller\ControllerBase;


class TransportDetailsController extends ControllerBase
{
    /* Get Third Party API response
    * @param  [string] url
    *  @return response object
    */
    public function getApiResponse($url)
    {

        $client = \Drupal::httpClient();
        $request = $client->get($url);
        $response = json_decode($request->getBody());
        //check for errors
        if ($response->code > 300) {
            return false;
        }

        return $response;
    }

    /* List all routes
    * 
    */
    public function allRoutes()
    {

        $url = 'https://api-v3.mbta.com/routes';
        $response = $this->getApiResponse($url);
        if (!$response) {
            return array(
                '#theme' => 'route_list',
                '#title' => 'Please try again later!'
            );
        }

        if (empty($response->data)) {
            return array(
                '#theme' => 'route_list',
                '#title' => 'No Routes information is found!'
            );
        }

        foreach ($response->data as $key => $value) {

            $info[$value->attributes->description][$key]['long_name'] = $value->attributes->long_name;
            $info[$value->attributes->description][$key]['color'] = $value->attributes->color;
            $info[$value->attributes->description][$key]['id'] = $value->id;
        }

        return array(
            '#theme' => 'route_list',
            '#info' => $info,
            '#title' => 'All Available Routes'
        );
    }

    /* List Schedule for a route
    * @param  [string] id
    * @param  [string] time
    */
    public function schedule($id, $time = null)
    {
        //set time for pagination of the schedule
        date_default_timezone_set('US/Eastern');
        $currenttime = date('H:i:s');
        if ($time) {
            $currenttime = date('H:i:s', $time);
        } else {
            date_default_timezone_set('US/Eastern');
            $currenttime = date('H:i:s');
        }


        $nexttime =  date('H:i:s', strtotime("+15 minutes", strtotime($currenttime)));
        $prevtime =  date('H:i:s', strtotime("-15 minutes", strtotime($currenttime)));

        list($hrs, $mins, $secs) = explode(':', $currenttime);
        list($n_hrs, $n_mins, $n_secs) = explode(':', $nexttime);

        //get schedule data from API
        $url = 'https://api-v3.mbta.com/schedules?include=route&filter%5Bmin_time%5D=' . $hrs . '%3A' . $mins . '&filter%5Bmax_time%5D=' . $n_hrs . '%3A' . $n_mins . '&filter%5Broute%5D=' . $id;
        $response = $this->getApiResponse($url);

        if (!$response) {
            return array(
                '#theme' => 'schedule',
                '#title' => 'Please try again later!'
            );
        }

        if (empty($response->data)) {
            return array(
                '#theme' => 'schedule',
                '#title' => 'No Transportation has been scheduled!'
            );
        }

        //Get the long name of the route
        $title = $response->included[0]->attributes->long_name . "'s Schedule for " . $currenttime;

        //Get the direction names
        $direction = array();
        foreach ($response->included[0]->attributes->direction_destinations as $key => $value) {
            $direction[$key] = $value;
        }

        foreach ($response->data as $key => $value) {

            $info[$key]['arrival_time'] = $value->attributes->arrival_time;
            $info[$key]['departure_time'] = $value->attributes->departure_time;
            $info[$key]['direction'] = $direction[$value->attributes->direction_id];
            $info[$key]['time'] = strtotime($currenttime);
        }
        $page_attributes['title'] = $title;
        $page_attributes['route_id'] = $id;
        $page_attributes['nexttime'] = strtotime($nexttime);
        $page_attributes['prevtime'] = strtotime($prevtime);

        return array(
            '#theme' => 'schedule',
            '#info' => $info,
            '#title' => $title,
            '#page_attributes' => $page_attributes,


        );
    }
}
