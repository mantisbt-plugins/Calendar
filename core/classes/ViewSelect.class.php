<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ViewSelectEvent
 *
 * @author ermolaev
 */
class ViewSelect extends ViewWeek{
    //put your code here
    private $bug_insert_id;
    public function __construct( $p_week, $p_user, $p_is_full_time, $p_bug_insert_id = 0 ) {
        parent::__construct( $p_week, $p_user, $p_is_full_time );
        
        $this->bug_insert_id = $p_bug_insert_id;
    }
    
    

    
}
