<?php namespace Ifnot\Dynatable;

/**
 * Class Dynatable
 */
class Dynatable {
	protected $query;
	protected $options;

	protected $columns;
	protected $sorts;
	protected $search;
	protected $defaultColumnSearch;
	protected $columnSearchs;

	/**
	 * @param $query
	 */
	public function __construct($query, $columns = array(), $inputs)
	{
		$this->query = $query;

		$this->options = array(
			'page-length' => (int) $inputs['perPage'],
			'page-number' => (int) $inputs['page'],
			'offset' => (int) $inputs['offset'],
			'sorts' => isset($inputs['sorts']) ? $inputs['sorts'] : null,
			'queries' => isset($inputs['queries']) ? $inputs['queries'] : array(),
		);

		$this->setDefaultHandlers($columns);
	}

	protected function setDefaultHandlers($columns)
	{
		// Define default handler for rendering content of column
		foreach($columns as $name) {
			$this->columns[$name] = function($row) use ($name) {
				return $row->$name;
			};
		}

		// Define default handler for ordering column
		foreach($columns as $name) {
			$this->sorts[$name] = function($query, $mode) use ($name) {
				return $query->orderBy($name, $mode);
			};
		}

		// Define default handler for global searching
		$this->search = function($query, $term) use ($columns) {
			$query->where(function($query) use ($term, $columns) {
				foreach($columns as $column) {
					$query->orWhere($column, 'LIKE', '%' . $term . '%');
				}
			});

			return $query;
		};

        // Define default handlers for column searching
        $this->defaultColumnSearch = function ($query, $column, $term) {
            return $query->where($column, 'LIKE', '%' . $term . '%');
        };
	}

	/**
	 * Define the display handler for the defined column
	 *
	 * @param $name
	 * @param $handler
	 *
	 * @return $this
	 */
	public function column($name, $handler)
	{
		$this->columns[$name] = $handler;

		return $this;
	}

	/**
	 * Define custom sort handler for the defined column
	 *
	 * @param $name
	 * @param $handler
	 *
	 * @return $this
	 */
	public function sort($name, $handler)
	{
		$this->sorts[$name] = $handler;

		return $this;
	}

	/**
	 * Define the search handler for the table
	 *
	 * @param $handler
	 */
	public function search()
	{
        $numargs = func_num_args();
        if($numargs == 1) {
            $this->search = func_get_arg(0);
        }
        elseif($numargs == 2) {
            $this->columnSearchs[func_get_arg(0)] = func_get_arg(1);
        }

		return $this;
	}

	/**
	 * @return bool
	 */
    protected function handleSearch()
	{
		if(!isset($this->options['queries']['search']))
			return false;

		$handler = $this->search;

		$this->query = $handler($this->query, $this->options['queries']['search']);
	}

    protected function handleColumnSearch()
    {
        foreach($this->options['queries'] as $column => $value) {

            // Do not reuse search field witch is used for global searching
            if($column == 'search') continue;

            if(isset($this->columnSearchs[$column])) {
                $handler = $this->columnSearchs[$column];
                $this->query = $handler($this->query, $value);
            }
            else {
                $handler = $this->defaultColumnSearch;
                $this->query = $handler($this->query, $column, $value);
            }
        }
    }

	/**
	 *
	 */
    protected function handleSorting()
	{
		if(!isset($this->options['sorts']))
			return false;

		foreach($this->options['sorts'] as $name => $mode) {
			$this->query = $this->sorts[$name]($this->query, $mode == 1 ? 'asc' : 'desc');
		}
	}

	/**
	 *
	 */
    protected function handlePagination()
	{
		$this->query->skip($this->options['offset'])->take($this->options['page-length']);
	}

	/**
	 * Passing Fluent records into the column handler for making the real record list
	 *
	 * @return array
	 */
	protected function getRecords()
	{
		$records = array();

		foreach($this->query->get() as $row) {
			$record = array();
			foreach($this->columns as $name => $handler) {
				$record[$name] = $handler($row);
			}
			$records[] = $record;
		}

		return $records;
	}

	/**
	 * @return string
	 */
	public function make()
	{
		$datas = array();

		// Apply the search filter
		$this->handleSearch();
        $this->handleColumnSearch();

		$datas['totalRecordCount'] = $this->query->count();
		$datas['queryRecordCount'] = $this->query->count();

		// Filter items by pagination
		$this->handleSorting();
		$this->handlePagination();

		$datas['records'] = $this->getRecords();

		return $datas;
	}

    /**
     * @return mixed
     */
    public function toSql()
    {
        // Apply the search filter
        $this->handleSearch();
        $this->handleColumnSearch();

        // Filter items by pagination
        $this->handleSorting();
        $this->handlePagination();

        return $this->query->toSql();
    }
}
