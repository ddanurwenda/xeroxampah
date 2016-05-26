<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Masjid
 *
 * @author Slurp
 */
class Masjid extends Member_Controller {

    function __construct() {
        parent::__construct();
        $this->load->model('masjid_model');
        $this->load->model('menu_model');
        $this->load->library('Datatables');
    }

    /**
     * serves autocomplete 
     */
    function search() {
        //explode term by space
        $terms = explode(' ', $this->input->get('term', true));
        foreach ($terms as $term) {
            $this->db->or_where('UPPER(name) LIKE', '%' . strtoupper($term) . '%');
            $this->db->or_where('UPPER(address) LIKE', '%' . strtoupper($term) . '%');
        }
        $r = $this->db
                ->get('masjid')
                ->result_array();
        $ret = [];
        foreach ($r as $i) {
            //craft return
            $i['id'] = $i['masjid_id'] + 0;
            $ret[] = $i;
        }
        echo json_encode($ret);
    }

    function add() {
        $data['breadcrumb'] = $this->menu_model->create_breadcrumb(4);
        $data['title'] = 'Tambah Masjid';
        $this->template->display('masjid/add_view', $data);
    }

    function index() {
        $data['breadcrumb'] = $this->menu_model->create_breadcrumb(4);
        $data['title'] = 'tr.db | Masjid';
        $this->template->display('masjid/table_view', $data);
    }

    function edit($id) {
        //load form and populate
        $data['breadcrumb'] = $this->menu_model->create_breadcrumb(4);
        $data['title'] = 'Ubah Masjid';
        $data['edit_id'] = $id;
        $this->template->display('masjid/add_view', $data);
    }

    /**
     * Server-side processing for datatables
     */
    function dt() {
        if ($this->input->is_ajax_request()) {
            //ajax only
            $this->datatables
                    ->select('name,address,city,masjid_id')
                    ->add_column('DT_RowId', 'row_$1', 'masjid_id')
                    ->from('masjid');
            echo $this->datatables->generate();
        }
    }

    //REST-like
    function submit() {
        $id = $this->input->post('masjid_id');
        $nama = $this->input->post('name');
        $address = $this->input->post('address');
        $city = $this->input->post('city');
        if ($id) {
            //edit
            if ($this->masjid_model->update($id, $nama, $address, $city)) {
                //update to neo4j
                postNeoQuery($this->masjid_model->neo4j_update_query($id, $nama, $address, $city));
                if ($this->input->is_ajax_request()) {
                    echo json_encode([$this->security->get_csrf_token_name() => $this->security->get_csrf_hash()]);
                } else {
                    //back to table
                    redirect('masjid');
                }
            } else {
                echo 0;
            }
        } else {
            //add
            //insert to db
            if ($new_id = $this->masjid_model->create($nama, $address, $city)) {
                //insert to neo4j
                postNeoQuery($this->masjid_model->neo4j_insert_query($new_id));
                if ($this->input->is_ajax_request()) {
                    echo json_encode([$this->security->get_csrf_token_name() => $this->security->get_csrf_hash()]);
                } else {
                    //back to table
                    redirect('masjid');
                }
            } else {
                echo 0;
            }
        }
    }

    function get($id) {
        echo json_encode($this->masjid_model->get($id));
    }

    function delete($id) {
        if ($this->masjid_model->delete($id)) {
            postNeoQuery($this->masjid_model->neo4j_delete_query($id));
            echo 1;
        } else {
            echo 0;
        }
    }

}
