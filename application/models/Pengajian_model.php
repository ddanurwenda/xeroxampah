<?php

defined('BASEPATH')OR
        exit('No direct script access allowed');

/**
 * Model terkait table menu.
 *
 * @author Administrator
 */
class Pengajian_model extends CI_Model {

    public $table = 'pengajian';
    public $primary_key = 'pengajian_id';
    private $sequence = 'pengajian_pengajian_id_seq';

    public function __construct() {
        parent::__construct();
    }

    public function get($id) {
        $q = $this->db
                ->get_where($this->table, [$this->primary_key => $id]);
        if ($q->num_rows() > 0) {
            $r = $q->row();
            if ($r->masjid) {
                $r->lokasi = $this->db->get_where('masjid', ['masjid_id' => $r->masjid])->row()->name;
            }  if ($r->pesantren) {
                $r->lokasi .=', '. $this->db->get_where('school', ['school_id' => $r->pesantren])->row()->name;
            }
            return $r;
        } else {
            return null;
        }
    }

    public function delete($id) {
        return $this->db->delete($this->table, [$this->primary_key => $id]);
    }

    public function update($id, $topik, $masjid, $pesantren) {
        return $this->db->update(
                        $this->table, array(
                    'topik' => $topik,
                    'masjid' => $masjid,
                    'pesantren' => $pesantren
                        ), [$this->primary_key => $id]
        );
    }

    public function create($topik, $masjid, $pesantren) {
        $this->db->insert(
                $this->table, array(
            'topik' => $topik,
            'masjid' => $masjid,
            'pesantren' => $pesantren
                )
        );
        return $this->last_id();
    }

    private function last_id() {
        return $this->db->insert_id($this->sequence);
    }

    public function neo4j_insert_query($id) {
        $pengajian = $this->get($id);
        $prop = "topik:'" . addslashes($pengajian->topik) . "',";
        $prop.="pengajian_id:" . $id;
        $merge = "merge(Pengajian_$id:Pengajian{ $prop })";
        $match = "";
        $edge = "";
        if ($pengajian->masjid) {
            $masjid = $pengajian->masjid;
            $match.="match(m:Masjid{masjid_id:$masjid})";
            $edge.=$merge . "-[l1:Lokasi]->(m)";
        }
        if ($pengajian->pesantren) {
            $pesantren = $pengajian->pesantren;
            $match.="match(p:School{school_id:$pesantren})";
            $edge.=$merge . "-[l2:Lokasi]->(p)";
        }
        if (empty($edge)) {
            return $merge;
        } else {
            return "$match $edge";
        }
    }

    public function neo4j_delete_query($id) {
        return "match(n:Pengajian{pengajian_id:$id})detach delete n";
    }

    public function neo4j_update_query($id, $topik, $masjid, $pesantren) {
        $match = "match(p:Pengajian{pengajian_id:$id}),(p)-[d:Lokasi]->(x)";
        $newRel = "";
        if ($masjid) {
            $match.=",(m2:Masjid{masjid_id:$masjid})";
            $newRel = "merge(p)-[r1:Lokasi]->(m2)";
        }
        if ($pesantren) {
            $match.= ",(s2:School{school_id:$pesantren})";
            $newRel.="merge(p)-[r2:Lokasi]->(s2)";
        }
        //delete existing relationship, if any
        $delRel = "delete d ";
        //update this.properties
        $prop = "set p.topik='" . addslashes($topik) . "' ";
        //compile query
        $ret = $match . $delRel . $newRel . $prop;
        return $ret;
    }

}
