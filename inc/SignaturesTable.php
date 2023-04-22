<?php
class SignaturesTable
{
    private $table_name;
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'lif_signatures';
    }

    public function create_table()
    {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            name varchar(50) NULL,
            firstname varchar(50) NULL,
            lastname varchar(50) NULL,
            email varchar(255) NOT NULL,
            country varchar(50) NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function delete_table()
    {
        $table_name = $this->wpdb->prefix . 'lif_signatures';
        $sql = "DROP TABLE IF EXISTS $table_name";
        $this->wpdb->query($sql);
    }

    public function insert($data)
    {
        return $this->wpdb->insert($this->table_name, $data);
    }

    public function select($id)
    {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE id = %d",
            $id
        ));
    }

    public function update($id, $data)
    {
        return $this->wpdb->update($this->table_name, $data, array('id' => $id));
    }

    public function delete($id)
    {
        return $this->wpdb->delete($this->table_name, array('id' => $id));
    }

    public function getTotalByID($id)
    {
        $rows = $this->wpdb->get_results("SELECT * FROM $this->table_name WHERE form_id = $id");
        return count($rows);
    }
}