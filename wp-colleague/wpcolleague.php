<?php
/*
Plugin Name: Colleague Integration
Plugin URI: 
Description: Integrates Colleague job posting
Version: 1
Author: Aigars
*/

function log_to_file($text=''){
    file_put_contents(dirname(__FILE__)."/log.txt",  $text ."\n-------------------\n\n", FILE_APPEND);
}

function curr_convert($from = 'GBP', $to = 'GBP', $amount=0){
        //TODO
        return $amount;
    }

$jobs_id_exists = array();
$jobs_ref_exists = array();    
$jobs_id_added = array();
$jobs_ref_added = array();
$jobs_id_deleted = array();

function add_job($wpbb_params){
        global $wpdb;
        global $jobs_id_added, $jobs_ref_added, $jobs_id_exists, $jobs_ref_exists, $jobs_id_deleted;
        
        $process_array = array();

        // firstly add all terms we know about
        $duplicates = $wpdb->get_results(
            "select ID from wp_posts left join wp_postmeta on wp_posts.ID = wp_postmeta.post_id
              where meta_key = '_job_reference' 
              and meta_value = '".wp_strip_all_tags($wpbb_params->RequirementId)."';");  //and wp_posts.post_status = 'publish'

        /* check whether we have any job returned with the submitted reference */
        if (!empty($duplicates)) {
            /* output an error message, as a job with this job reference already exists and stop any further loading */
            $jobs_id_exists[] = $duplicates[0]->ID;
            $jobs_ref_exists[] = wp_strip_all_tags($wpbb_params->RequirementId);
            //log_to_file( 'Oops, this job was not added, as a job with this jobs job reference already exists. '."ID: ".$duplicates[0]->ID);
            //exit;
        }
        else {

            $wpbb_job_post_args = array(
                'post_type' => 'job_listing',
                'post_author' => 3,
                'post_title' => wp_strip_all_tags($wpbb_params->JobTitle),
                'post_content' => wp_kses($wpbb_params->JobDescription, wp_kses_allowed_html('post')),
                'post_status' => 'publish'
            );

            // set put publish date. Might be in the future
            //log_to_file("Created date: ".$wpbb_params->CreatedDate);
            if (isset($wpbb_params->CreatedDate)) {
                $date = str_replace(array('/',' '), '-', trim($wpbb_params->CreatedDate));
                $wpbb_job_post_args['post_date'] = date('Y-m-d H:i:s', strtotime($date));
                $wpbb_job_post_args['post_date_gmt'] = date('Y-m-d H:i:s', strtotime($date));
                
                
            }

            /*****************************************************
             * start adding the job post
             *****************************************************/

            /* insert the post returning the post id */
            $wpbb_job_post_id = wp_insert_post($wpbb_job_post_args);
            
            log_to_file("Inserted a job with ID: ".$wpbb_job_post_id);
            
            $jobs_id_added[] = $wpbb_job_post_id;
            $jobs_ref_added[] = wp_strip_all_tags($wpbb_params->RequirementId);

            /* check the post has been added */
            if ($wpbb_job_post_id != 0) {

                /* set the post meta data (custom fields) first for job reference */
                add_post_meta($wpbb_job_post_id, '_job_reference', wp_strip_all_tags($wpbb_params->RequirementId), true);
                
                //==add_post_meta($wpbb_job_post_id, '_company_name', wp_strip_all_tags($wpbb_params->CompanyName), true);
                
                

                /* set the post meta data (custom fields) for salary */
                
                if ((isset($wpbb_params->SalaryFrom) && floatval(wp_strip_all_tags($wpbb_params->SalaryFrom)) > 0) || (isset($wpbb_params->SalaryTo) && floatval(wp_strip_all_tags($wpbb_params->SalaryTo)) > 0)) {
                    $salary  = curr_convert($wpbb_params->Currency, 'GBP', floatval(wp_strip_all_tags($wpbb_params->SalaryFrom)));
                    if(isset($wpbb_params->SalaryTo) && floatval(wp_strip_all_tags($wpbb_params->SalaryTo)) > 0 && floatval(wp_strip_all_tags($wpbb_params->SalaryTo)) != floatval(wp_strip_all_tags($wpbb_params->SalaryFrom))){
                            if(empty($salary))$salary = "";
                            else $salary .= " - ";
                            $salary .= curr_convert($wpbb_params->Currency, 'GBP', floatval(wp_strip_all_tags($wpbb_params->SalaryTo)));
                    }
                    add_post_meta($wpbb_job_post_id, '_job_salary', $salary, true);
                }

                /* set the post meta data (custom fields) for the applitrak email address */
                /*not today
                if (isset($wpbb_params->ExternalApplicationEmail)) {
                    add_post_meta(
                        $wpbb_job_post_id,
                        '_job_aplitrak',
                        wp_strip_all_tags($wpbb_params->ExternalApplicationEmail),
                        true
                    );
                }
                */
                /* add the string location */
                if (isset($wpbb_params->AddressTown)) {
                    add_post_meta($wpbb_job_post_id, '_job_location', wp_strip_all_tags($wpbb_params->AddressTown, true));
                    
                    wp_set_object_terms($wpbb_job_post_id, wp_strip_all_tags($wpbb_params->AddressTown, true), 'job_listing_region');
                }
                
                

                /* add the date the job will expire on our site */
                /* 
                */
                if (isset($wpbb_params->CreatedDate)) {
                    $date = str_replace(array('/',' '), '-', trim($wpbb_params->CreatedDate));
                    //$wpbb_job_post_args['post_date'] = date('Y-m-d H:i:s', strtotime($date));
                    //add_post_meta($wpbb_job_post_id, '_job_expires', wp_strip_all_tags(date('Y-m-d', strtotime($date)+365*24*3600), true));
                }
                /*
                if (isset($wpbb_params->ExpiryDate)) {
                    $date = str_replace('/', '-', $wpbb_params->ExpiryDate);
                    
                }
                */

                /* set the post meta data (custom fields) standard email address for queries */
                //log_to_file("Email: ".$wpbb_params->PickedUpByUserEmail);
                add_post_meta($wpbb_job_post_id, '_application', wp_strip_all_tags($wpbb_params->PickedUpByUserEmail, true));
                
                
                //Set job type
                
                $job_types = array('Permanent'=>19, 'Contract'=>20);
                
                if(array_key_exists(wp_strip_all_tags($wpbb_params->JobType, true), $job_types)){
                    //log_to_file("wp_set_object_terms( ".$wpbb_job_post_id.", ".$job_types[wp_strip_all_tags($wpbb_params->JobType, true)].", 'job_listing_type')");
                    //log_to_file(wp_strip_all_tags($wpbb_params->PickedUpByUserEmail, true));
                    wp_set_object_terms( $wpbb_job_post_id, $job_types[wp_strip_all_tags($wpbb_params->JobType, true)], 'job_listing_type');
                    
                }
                
                if(wp_strip_all_tags($wpbb_params->JobType)=="Permanent"){
                    add_post_meta($wpbb_job_post_id, '_job_salary_period', 0, true);
                }else{
                    add_post_meta($wpbb_job_post_id, '_job_salary_period', 3, true);
                }
                
                wp_set_object_terms( $wpbb_job_post_id, 10, 'job_listing_category');
                

                // now add our options
                /*
                
                global $wpdb;

                // broadbean will send a comma separated list of option ids. each id maps to a
                // taxnomy. for example 1200 is africa, 262 is Contract etc.

                // get all option ids avaiavle to us.
                $results = $wpdb->get_results("SELECT * FROM wp_taxonomymeta WHERE meta_key = 'option_id'", OBJECT);

                // make a map so we dont have to go back the the databse each time.
                $arr_map = array();
                foreach ($results as $obj_result) {
                    $arr_map[$obj_result->taxonomy_id] = $obj_result->meta_value;
                }

                $arr_types = array();
                $arr_locations = array();
                $arr_categories = array();

                // go through the option ids provided to us
                foreach (explode(',', $wpbb_params->Options) as $option) {

                    // if its found in the map then we need to add this to our job
                    $found_terms = array_keys($arr_map, $option);

                    // add each term to out array for adding later
                    foreach ($found_terms as $term_id) {
                        if ($term_id) {
                            $term = $wpdb->get_row(
                                $wpdb->prepare(
                                    "SELECT t.*, tax.taxonomy FROM $wpdb->terms AS t left join
                            wp_term_taxonomy as tax on
                            tax.term_id = t.term_id  WHERE t.term_id = %s LIMIT 1",
                                    $term_id
                                )
                            );

                            if ($term && $term->taxonomy == 'job_listing_category') {
                                $arr_categories[] = $term_id;
                            } else {
                                if ($term && $term->taxonomy == 'job_listing_type') {
                                    $arr_types[] = $term_id;
                                } else {
                                    if ($term && $term->taxonomy == 'job_listing_region') {
                                        $arr_locations[] = $term_id;
                                    }
                                }
                            }
                        }
                    }
                }
                
                if (!empty($arr_types)) {
                    wp_set_post_terms($wpbb_job_post_id, $arr_types, 'job_listing_type');
                }

                if (!empty($arr_locations)) {
                    wp_set_post_terms($wpbb_job_post_id, $arr_locations, 'job_listing_region');
                }

                if (!empty($arr_categories)) {
                    wp_set_post_terms($wpbb_job_post_id, $arr_categories, 'job_listing_category');
                }
                */
                log_to_file( '<p class="success">' . apply_filters(
                        'wpbb_job_added_success_message',
                        'Success! - This Job has been added and has a post ID: {' . $wpbb_job_post_id . '} .
                                The permalink to this job is url: [' . get_permalink($wpbb_job_post_id)
                    ) . ']</p>');


                
                /* no post id exists for the newly created job - an error occured */
            } else {

                log_to_file( '<p class="error">' . apply_filters(
                        'wpbb_job_added_failure_message',
                        'There was an error, the job was not published.'
                    ) . '</p>');

            } // end if insert post has an id

        }
        //log_to_file("Jobs ids: ".print_r($jobs_id_added, true)."\n\n"."Jobs ref: ".print_r($jobs_ref_added, true));
    }
    
function delete_jobs(){
    global $wpdb;
    global $jobs_id_added, $jobs_ref_added, $jobs_id_exists, $jobs_ref_exists, $jobs_id_deleted;
    if(empty($jobs_id_added) && empty($jobs_id_exists))return false;
    if(empty($jobs_ref_added) && empty($jobs_ref_exists))return false;
              
    $to_delete = $wpdb->get_results(
            "select ID,meta_value from wp_posts left join wp_postmeta on wp_posts.ID = wp_postmeta.post_id
              where post_author='3' 
              and ID NOT IN (".implode(",", array_merge($jobs_id_exists,$jobs_id_added)).")
              and  meta_key = '_job_reference' 
              and meta_value NOT IN (".implode(",", array_merge($jobs_ref_exists, $jobs_ref_added)).")");
              
              
    $jobs_id_deleted = $to_delete;
    //log_to_file("To delete: ".print_r($to_delete, true));
    foreach($to_delete as $del){
        wp_delete_post($del->ID, true);
    }
    
    
    
}


function main_load_colleague() {
        global $jobs_id_added, $jobs_ref_added, $jobs_id_exists, $jobs_ref_exists, $jobs_id_deleted;
        $data_files = glob(ABSPATH.'xml_jobs/*.xml',GLOB_BRACE);
        foreach ($data_files as $file) {
            $file_str = file_get_contents($file);
            //$file_str = str_replace(array(">/SalaryTo>", "</AddressCity>"), array("></SalaryTo>", "</AddressTown>"), $file_str);
            //$file_str = str_replace(array("“", "”", "", ""), array('"', '"', '"', '"'), $file_str);
            $file_str = '<?xml version="1.0" encoding="UTF-8"?>'.$file_str;
            //$file_str = utf8_encode($file_str);
            
            $regex = <<<'END'
/
  (
    (?: [\x00-\x7F\x95\x92\xA3\x96\x85]                 # single-byte sequences   0xxxxxxx
    |   [\xC0-\xDF][\x80-\xBF]      # double-byte sequences   110xxxxx 10xxxxxx
    |   [\xE0-\xEF][\x80-\xBF]{2}   # triple-byte sequences   1110xxxx 10xxxxxx * 2
    |   [\xF0-\xF7][\x80-\xBF]{3}   # quadruple-byte sequence 11110xxx 10xxxxxx * 3 
    ){1,100}                        # ...one or more times
  )
| .                                 # anything else
/x
END;
$file_str = preg_replace($regex, '$1', $file_str);
$file_str = str_replace(
                array("\x95", "\x92", "\xA3", "\x96", "\x85"), 
                array('&bull;', '&prime;', '£', '-', '...'), 
                $file_str);
            
            $config = array(
                'indent' => true,
                'clean' => true,
                'input-xml'  => true,
                'output-xml' => true,
                'wrap'       => false
                );

            $tidy = new Tidy();
            $file_str = $tidy->repairString($file_str, $config);

            file_put_contents(dirname(__FILE__)."/last_parsed.xml", $file_str);
            $xml = simplexml_load_string($file_str, "SimpleXMLElement", LIBXML_NOERROR |  LIBXML_ERR_NONE);
            if($xml===false){
                log_to_file("Bad XML file: ".$file);
            }else{
                log_to_file("Parsing file: ".$file);
                foreach($xml->Requirement as $Req){
                    add_job($Req);
                    //log_to_file(print_r($Req, true));
                }
                delete_jobs();
                log_to_file("At ".date("d.M.Y H:i:s")." jobs ids exist: ".print_r($jobs_id_exists, true)."\n"."Jobs ref exist: ".print_r($jobs_ref_exists, true)."\n"."Jobs ids added: ".print_r($jobs_id_added, true)."\n"."Jobs ref added: ".print_r($jobs_ref_added, true)."\n"."Jobs ids deleted: ".print_r($jobs_id_deleted, true));
    
            }
        }
        
}


// [[[[ START HERE ]]]]
add_action( 'colleague_add_jobs_cron', 'main_load_colleague' ); //this will be added to cron
register_deactivation_hook( __FILE__, 'deactivate_func_colleague' );


add_filter( 'cron_schedules', 'add_cron_interval_colleague' ); //create new schedule
function add_cron_interval_colleague( $schedules ) {
    $schedules['minute'] = array(
        'interval' => 600,
        'display'  => esc_html__( 'Every 10 minutes' ),
    );
 
    return $schedules;
}

if( !wp_next_scheduled( 'colleague_add_jobs_cron' ) ) { //add to schedule
    wp_schedule_event( time(), 'minute', 'colleague_add_jobs_cron' );
}

function deactivate_func_colleague(){
    
     wp_unschedule_event( wp_next_scheduled( 'colleague_add_jobs_cron' ), 'colleague_add_jobs_cron' );
    
}
