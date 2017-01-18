<?php
/**
 * @version    SVN:
 * @package    Com_Tjvendors
 * @author     Techjoomla  <contact@techjoomla.com>
 * @copyright  Copyright (c) 2009-2017 TechJoomla. All rights reserved.
 * @license    GNU General Public License version 2 or later.
 */
defined('_JEXEC') or die;

jimport('joomla.application.component.modellist');

/**
 * Methods supporting a list of Tjvendors records.
 *
 * @since  1.6
 */
class TjvendorsModelPayouts extends JModelList
{
/**
	* Constructor.
	*
	* @param   array  $config  An optional associative array of configuration settings.
	*
	* @see        JController
	* @since      1.6
	*/
	public function __construct($config = array())
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array(
				'id', 'pass.`id`',
				'vendor_id', 'vendors.`vendor_id`',
				'total', 'pass.`total`',
				'currency', 'fees.`currency`',
			);
		}

		parent::__construct($config);
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   string  $ordering   Elements order
	 * @param   string  $direction  Order direction
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// Initialise variables.
		$app = JFactory::getApplication('administrator');

		// Set ordering.
		$orderCol = $app->getUserStateFromRequest($this->context . '.filter_order', 'filter_order');

		if (!in_array($orderCol, $this->filter_fields))
		{
			$orderCol = 'pass.id';
		}

		$this->setState('list.ordering', $orderCol);

		// Load the filter state.
		$search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
		$this->setState('filter.search', $search);

		$published = $app->getUserStateFromRequest($this->context . '.filter.state', 'filter_published', '', 'string');
		$this->setState('filter.state', $published);

		// Load the parameters.
		$params = JComponentHelper::getParams('com_tjvendors');
		$this->setState('params', $params);

		// List state information.
		parent::populateState('pass.id', 'asc');
	}

	/**
	 * Build an SQL query to load the list data.
	 *
	 * @return   JDatabaseQuery
	 *
	 * @since    1.6
	 */

	protected function getListQuery()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$input = JFactory::getApplication()->input;
		$client = $input->get('client', '', 'STRING');

		$query->select($db->quoteName(array('vendors.vendor_id','pass.id','fees.currency','vendors.vendor_title','pass.total')))
			->from($db->quoteName('#__tjvendors_vendors', 'vendors'))
			->join('LEFT', $db->quoteName('#__tjvendors_fee', 'fees') .
			' ON (' . $db->quoteName('vendors.vendor_id') . ' = ' . $db->quoteName('fees.vendor_id') . ')')
			->join('LEFT', $db->quoteName('#__tjvendors_passbook', 'pass') .
			' ON (' . $db->quoteName('fees.vendor_id') . ' = ' . $db->quoteName('pass.vendor_id') .
			' AND ' . $db->quoteName('fees.currency') . ' = ' . $db->quoteName('pass.currency') . ')')
			->where($db->quoteName('vendors.vendor_client') . ' = ' . "'$client'" . 'AND' . $db->quoteName('pass.id') . ' is not null');
		$db->setQuery($query);
		$rows = $db->loadAssocList();

		// Filter by search in title
		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			if (stripos($search, 'id:') === 0)
			{
				$query->where('pass.id = ' . (int) substr($search, 3));
			}
			else
			{
				$search = $db->Quote('%' . $db->escape($search, true) . '%');
				$query->where('(vendors.vendor_title LIKE ' . $search .
							'OR fees.currency LIKE' . $search .
							'OR pass.total LIKE' . $search .
							'OR pass.id LIKE' . $search . ')');
			}
		}

		// Add the list ordering clause.
		$orderCol  = $this->state->get('list.ordering');
		$orderDirn = $this->state->get('list.direction');

		if ($orderCol && $orderDirn)
		{
			$query->order($db->escape($orderCol . ' ' . $orderDirn));
		}

		return $query;
	}

	/**
	 * get the list items
	 *
	 * @return   items
	 *
	 * @since    1.6
	 */

	public function getItems()
	{
		$items = parent::getItems();

		// Get latest payout amount for provided vendor and currency
		foreach ($items as $i => $item)
		{
			foreach ($items as $j => $tempItem)
			{
				if (($item->vendor_id == $tempItem->vendor_id) && ($item->currency == $tempItem->currency))
				{
					if ($item->id > $tempItem->id)
					{
						unset($items[$j]);
					}
				}
			}
		}

		return $items;
	}
}