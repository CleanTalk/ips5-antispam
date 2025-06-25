<?php

namespace Cleantalk\Custom\Db;
use IPS\Db as IpsDB;

class Db extends \Cleantalk\Common\Db\Db {
    private $query;

    /**
     * Alternative constructor.
     * Initilize Database object and write it to property.
     * Set tables prefix.
     */
    protected function init() {
        $this->prefix = IpsDB::i()->prefix;
    }

    /**
     * Set $this->query string for next uses
     *
     * @param $query
     * @return $this
     */
    public function setQuery( $query ) {
        $this->query = $query;
        return $this;
    }

    /**
     * Safely replace place holders
     *
     * @param string $query
     * @param array  $vars
     *
     * @return bool|\mysqli_result|\mysqli_stmt|string
     */
    public function prepare( $query, $vars = array() ) {
        $this->result = IpsDB::i()->preparedQuery($query, $vars);
        return $this->result;
    }

    /**
     * Run any raw request     *
     *
     * @param string $query
     * @param false $return_affected
     * @return bool|int Raw result
     */
    public function execute($query, $return_affected = false) {
        $this->result = IpsDB::i()->query($query);
        return $this->result instanceof \mysqli_result ? $this->result->fetch_all() : $this->result;
    }

    /**
     * Fetchs first column from query.
     * May receive raw or prepared query.
     *
     * @param bool $query
     * @param bool $response_type
     *
     * @return array|object|void|null
     */
    public function fetch( $query = false, $response_type = false ) {
        $this->result = IpsDB::i()->query($query)->fetch_row()[0];

        return $this->result;
    }

    /**
     * Fetchs all result from query.
     * May receive raw or prepared query.
     *
     * @param bool $query
     * @param bool $response_type
     *
     * @return array|object|null
     */
    public function fetchAll( $query = false, $response_type = false ) {
        foreach (IpsDB::i()->query($query) as $row) {
            $this->result[] = $row;
        }
        return $this->result;
    }

    public function getAffectedRows()
    {
        // TODO: Implement getAffectedRows() method.
    }
}
