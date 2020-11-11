<?php
class Services {
	public $settings = array(
		'admin_menu_category' => 'General',
		'admin_menu_name' => 'Services',
		'admin_menu_icon' => '<i class="icon-tasks"></i>',
		'description' => 'Shows a list of services to users and admins. Also shows the service information including the control panel for the service if applicable.',
		'permissions' => array(
			'Services_Create',
			'Services_Suspend',
			'Services_Unsuspend',
			'Services_Terminate',
			'Services_Edit',
			'Services_Generate_Invoice',
			'Services_Change_Plan',
			'Services_Move_Service',
			'Services_ControlPanel'
		) ,
		'user_menu_name' => 'My Services',
		'user_menu_icon' => '<i class="icon-tasks"></i>',
	);
	function admin_area() {
		global $billic, $db;
		if (isset($_GET['ID'])) {
			$service = $db->q('SELECT * FROM `services` WHERE `id` = ?', $_GET['ID']);
			$service = $service[0];
			if (empty($service)) {
				err("Service " . $_GET['ID'] . " does not exist");
			}
			$user_row = $db->q('SELECT * FROM `users` WHERE `id` = ?', $service['userid']);
			$user_row = $user_row[0];
			$billic->module($service['module']);
			$billic->set_title('Service ' . $service['username'] . (empty($service['username']) ? 'ID ' . $service['id'] : ''));
			echo '<img src="' . $billic->avatar($user_row['email'], 100) . '" class="pull-left" style="margin: 5px 5px 5px 0"><h3><a href="/Admin/Users/ID/' . $user_row['id'] . '/">' . $user_row['firstname'] . ' ' . $user_row['lastname'] . '' . (empty($user_row['companyname']) ? '' : ' - ' . $user_row['companyname']) . '</a> &raquo; ' . $billic->service_type($service) . '</h3><div class="btn-group" role="group" aria-label="Service Actions">';
			if ($billic->user_has_permission($billic->user, 'Services_ControlPanel')) {
				echo '<a href="/Admin/Services/ID/' . $service['id'] . '/" class="btn btn-info"><i class="icon-gears-setting"></i> Control Panel</a>';
			}
			if ($billic->user_has_permission($billic->user, 'Services_Edit')) {
				echo '<a href="/Admin/Services/ID/' . $service['id'] . '/Do/Edit/" class="btn btn-default"><i class="icon-edit-write"></i> Edit</a>';
			}
			if ($billic->user_has_permission($billic->user, 'Services_Change_Plan')) {
				echo '<a href="/Admin/Services/ID/' . $service['id'] . '/Do/ChangePlan/" class="btn btn-default"><i class="icon-archive"></i> Change Plan</a>';
			}
			if (($service['domainstatus'] == 'Pending' || $service['domainstatus'] == 'Terminated' || $service['domainstatus'] == 'Cancelled') && $billic->user_has_permission($billic->user, 'Services_Create') && method_exists($billic->modules[$service['module']], 'create')) {
				echo '<a href="/Admin/Services/ID/' . $service['id'] . '/Do/Create/" class="btn btn-success" onclick="return confirm(\'Are you sure: CREATE?\');"><i class="icon-plus"></i> Create</a>';
			}
			if ($service['domainstatus'] == 'Active' && $billic->user_has_permission($billic->user, 'Services_Suspend') && method_exists($billic->modules[$service['module']], 'suspend')) {
				echo '<a href="/Admin/Services/ID/' . $service['id'] . '/Do/Suspend/" class="btn btn-warning" onclick="return confirm(\'Are you sure: SUSPEND?\');"><i class="icon-pause"></i> Suspend</a>';
			}
			if ($service['domainstatus'] == 'Suspended' && $billic->user_has_permission($billic->user, 'Services_Unsuspend') && method_exists($billic->modules[$service['module']], 'unsuspend')) {
				echo '<a href="/Admin/Services/ID/' . $service['id'] . '/Do/Unsuspend/" class="btn btn-success" onclick="return confirm(\'Are you sure: UNSUSPEND?\');"><i class="icon-play"></i> Unsuspend</a>';
			}
			if (($service['domainstatus'] == 'Active' || $service['domainstatus'] == 'Suspended') && $billic->user_has_permission($billic->user, 'Services_Terminate') && method_exists($billic->modules[$service['module']], 'terminate')) {
				echo '<a href="/Admin/Services/ID/' . $service['id'] . '/Do/Terminate/" class="btn btn-danger" onclick="return confirm(\'Are you sure: TERMINATE?\');"><i class="icon-remove"></i> Terminate</a>';
			}
			if ($billic->user_has_permission($billic->user, 'Services_Generate_Invoice')) {
				echo '<a href="/Admin/Services/ID/' . $service['id'] . '/Do/GenerateInvoice/" class="btn btn-default" onclick="return confirm(\'This will generate the next invoice in advance if it has not already been created. Are you sure?\');"><i class="icon-tag"></i> Generate Invoice Early</a>';
			}
			if ($billic->user_has_permission($billic->user, 'Services_Move_Service')) {
				echo '<a href="/Admin/Services/ID/' . $service['id'] . '/Do/MoveService/" class="btn btn-default"><i class="icon-exchange"></i> Move Service</a>';
			}
			echo '</div><div style="clear:both"></div>';
			if ($billic->user_has_permission($billic->user, 'Services_Generate_Invoice')) {
				if ($_GET['Do'] == 'GenerateInvoice') {
					$invoiceid = $db->q('SELECT `invoiceid` FROM `invoiceitems` WHERE `relid` = ? ORDER BY `id` DESC LIMIT 1', $service['id']);
					$invoiceid = $invoiceid[0]['invoiceid'];
					$invoicecount = $db->q('SELECT COUNT(*) FROM `invoices` WHERE `id` = ? AND `status` = ?', $invoiceid, 'Unpaid');
					if ($invoicecount[0]['COUNT(*)'] > 0) {
						err('Unable to generate an invoice because <a href="/Admin/Invoices/ID/' . $invoiceid . '/">Invoice #' . $invoiceid . '</a> has already been generated.');
					}
					$billic->module('Invoices');
					$invoiceid = $billic->modules['Invoices']->generate(array(
						'service' => $service,
						'user' => $user_row,
						'duedate' => $service['nextduedate'],
					));
					$billic->redirect('/Admin/Invoices/ID/' . $invoiceid . '/');
				}
			}
			if ($billic->user_has_permission($billic->user, 'Services_Edit')) {
				if ($_GET['Do'] == 'Edit') {
					if (empty($service['coupon_name'])) {
						$coupon_data = array();
					} else {
						$coupon_data = json_decode($service['coupon_data'], true);
					}
					$statuses = array(
						'Pending',
						'Active',
						'Suspended',
						'Terminated',
						'Cancelled',
						'Fraud'
					);
					if (isset($_POST['update'])) {
						$nextduedate = @strtotime($_POST['nextduedate'] . ' ' . $_POST['nextduetime']);
						if (empty($_POST['donotsuspenddate'])) {
							$donotsuspenduntil = 0;
						} else {
							$donotsuspenduntil = @strtotime($_POST['donotsuspenddate'] . ' ' . $_POST['donotsuspendtime']);
						}
						/*if (empty($_POST['username'])) {
						$billic->errors[] = 'Username can not be empty';
						} else*/
						if (!in_array($_POST['status'], $statuses)) {
							$billic->errors[] = 'Invalid status';
						} else if (!$nextduedate) {
							$billic->errors[] = 'Invalid "next due date"';
						} else if (!empty($_POST['donotsuspenddate']) && !$donotsuspenduntil) {
							$billic->errors[] = 'Invalid "do not suspend until" date';
						}
						if (isset($_POST['import_data'])) {
							$import_data = json_encode($_POST['import_data']);
						} else {
							$import_data = '';
						}
						$coupon_data['recurring'] = $_POST['coupon_recurring'];
						$coupon_data['recurring_type'] = $_POST['coupon_recurring_type'];
						$coupon_data['remaining_billing_cycles'] = $_POST['remaining_billing_cycles'];
						if ($coupon_data['remaining_billing_cycles'] < 0) {
							$coupon_data['remaining_billing_cycles'] = 0;
						}
						if (empty($billic->errors)) {
							$db->q('UPDATE `services` SET `username` = ?, `domain` = ?, `domainstatus` = ?, `suspension_reason` = ?, `nextduedate` = ?, `donotsuspenduntil` = ?, `amount` = ?, `billingcycle` = ?, `import_data` = ?, `module` = ?, `coupon_data` = ? WHERE `id` = ?', $_POST['username'], $_POST['domain'], $_POST['status'], $_POST['suspension_reason'], $nextduedate, $donotsuspenduntil, $_POST['amount'], $_POST['billingcycle'], $import_data, $_POST['module'], json_encode($coupon_data) , $service['id']);
							if (is_array($_POST['serviceoption_value'])) {
								foreach ($_POST['serviceoption_value'] as $k => $v) {
									$db->q('UPDATE `serviceoptions` SET `value` = ? WHERE `id` = ?', $v, $k);
								}
							}
							$billic->status = 'updated';
							$service = $db->q('SELECT * FROM `services` WHERE `id` = ?', $_GET['ID']);
							$service = $service[0];
						}
					}
					if (!empty($service['error'])) {
						if (isset($_POST['clear_error'])) {
							$db->q('UPDATE `services` SET `error` = \'\', `errorfunc` = \'\' WHERE `id` = ?', $service['id']);
							$billic->status = 'updated';
						} else {
							echo '<form method="POST"><table class="table table-striped">';
							echo '<tr><th colspan="2">Service Automation Error</th></tr>';
							echo '<tr><td>Error Message:</td><td>' . safe($service['error']) . '</td></tr>';
							if (!empty($service['errorfunc'])) {
								echo '<tr><td>Error Caused By:</td><td>';
								switch ($service['errorfunc']) {
									case 'create':
										echo 'Attempting to <b>CREATE</b> the service';
									break;
									case 'suspend':
										echo 'Attempting to <b>SUSPEND</b> the service';
									break;
									case 'unsuspend':
										echo 'Attempting to <b>UNSUSPEND</b> the service';
									break;
									case 'terminate':
										echo 'Attempting to <b>TERMINATE</b> the service';
									break;
									default:
										echo safe($service['errorfunc']);
									break;
								}
								echo '</td></tr>';
							}
							echo '<tr><td></td><td><input type="submit" class="btn btn-default" name="clear_error" value="Clear error &raquo;"></td></tr>';
							echo '</table></form>';
							echo '<br><br>';
						}
					}
					$billic->show_errors();
					echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/css/bootstrap-datepicker.min.css">';
					echo '<script>addLoadEvent(function() { $.getScript( "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/js/bootstrap-datepicker.min.js", function( data, textStatus, jqxhr ) { $( "#nextduedate" ).datepicker({ format: "yyyy-mm-dd" }); $( "#donotsuspenddate" ).datepicker({ format: "yyyy-mm-dd" }); }); });</script>';
					echo '<form method="POST" class="form-inline">';
					if ($service['module'] == 'RemoteBillicService') {
						$import_data = json_decode($service['import_data'], true);
						echo '<table class="table table-striped">';
						echo '<tr><th colspan="2">Service Import Data</th></tr>';
						echo '<tr><td width="130">Domain:</td><td><input type="text" class="form-control" name="import_data[domain]" value="' . safe($import_data['domain']) . '"></td></tr>';
						echo '<tr><td width="130">Email:</td><td><input type="text" class="form-control" name="import_data[email]" value="' . safe($import_data['email']) . '"></td></tr>';
						echo '<tr><td width="130">API Key:</td><td><input type="text" class="form-control" name="import_data[apikey]" value="' . safe($import_data['apikey']) . '"></td></tr>';
						echo '<tr><td width="130">Service ID:</td><td><input type="text" class="form-control" name="import_data[serviceid]" value="' . safe($import_data['serviceid']) . '"></td></tr>';
						echo '<tr><td width="130">Import Hash:</td><td><input type="text" class="form-control" name="import_data[hash]" value="' . safe($import_data['hash']) . '"></td></tr>';
						echo '</table><br>';
					}
					echo '<table class="table table-striped">';
					echo '<tr><th colspan="2">Edit Service</th></tr>';
					echo '<tr><td width="130">Service ID:</td><td>' . safe($service['id']) . '</td></tr>';
					echo '<tr><td width="130">Username:</td><td><input type="text" class="form-control" name="username" value="' . safe($service['username']) . '"></td></tr>';
					echo '<tr><td>Domain:</td><td><input type="text" class="form-control" name="domain" value="' . safe($service['domain']) . '" style="width:350px;"></td></tr>';
					echo '<tr><td>Status:</td><td><select class="form-control" name="status">';
					foreach ($statuses as $status) {
						echo '<option value="' . $status . '"' . ($status == $service['domainstatus'] ? ' selected' : '') . '>' . $status . '</option>';
					}
					echo '</select></td></tr>';
					echo '<tr><td>Suspension Reason:</td><td><input type="text" class="form-control" name="suspension_reason" value="' . safe($service['suspension_reason']) . '"></td></tr>';
					echo '<tr><td>Recurring Amount:</td><td><div class="input-group" style="width: 200px"><span class="input-group-addon">' . get_config('billic_currency_prefix') . '</span><input type="text" class="form-control" name="amount" value="' . safe($service['amount']) . '"><span class="input-group-addon">' . get_config('billic_currency_suffix') . '</span></div></td></tr>';
					echo '<tr><td>Setup Amount:</td><td><div class="input-group" style="width: 200px"><span class="input-group-addon">' . get_config('billic_currency_prefix') . '</span><input type="text" class="form-control" name="amount" value="' . safe($service['setup']) . '"  disabled="disabled"><span class="input-group-addon">' . get_config('billic_currency_suffix') . '</span></div></td></tr>';
					if (!empty($service['import_data'])) {
						$import_data = json_decode($service['import_data'], true);
						if ($import_data == null || empty($import_data)) {
							err('The exported plan data is corrupt for service ' . $service['id']);
						}
						$billingcycles = $db->q('SELECT * FROM `billingcycles` WHERE `import_hash` = ?', $import_data['hash']);
					} else {
						$billingcycles = $db->q('SELECT * FROM `billingcycles` WHERE `import_hash` = ?', '');
					}
					echo '<tr><td>Module:</td><td><select class="form-control" name="module">';
					$modules = $billic->module_list_function('create');
					foreach ($modules as $module) {
						echo '<option value="' . $module['id'] . '"' . ($module['id'] == $service['module'] ? ' selected' : '') . '>' . $module['id'] . '</option>';
					}
					echo '</select></td></tr>';
					echo '<tr><td>Billing Cycle:</td><td><select class="form-control" name="billingcycle" style="font-family: monospace">';
					foreach ($billingcycles as $billingcycle) {
						$string1 = $billingcycle['name'];
						$string1.= str_repeat('&nbsp;', (31 - strlen($string1)));
						$string2 = round($billingcycle['multiplier'], 2) . 'x Price';
						$string2.= str_repeat('&nbsp;', (15 - strlen($string2)));
						$string3 = $billic->time_ago(time() - $billingcycle['seconds']);
						$string3.= str_repeat('&nbsp;', (15 - strlen($string3)));
						echo '<option value="' . $billingcycle['name'] . '"' . ($billingcycle['name'] == $service['billingcycle'] ? ' selected' : '') . '>' . $string1 . ' ' . $string2 . ' ' . $string3 . ' ' . $billingcycle['discount'] . '% Discount</option>';
					}
					echo '</select></td></tr>';
					echo '<tr><td>Order Date:</td><td>' . safe(date('Y-m-d H:i', $service['regdate'])) . '</td></tr>';
					$nextduedate = date('Y-m-d', $service['nextduedate']);
					$nextduetime = date('H:i', $service['nextduedate']);
					echo '<tr><td>Next Due Date:</td><td><div class="input-group"><input type="text" class="form-control" id="nextduedate" name="nextduedate" value="' . safe($nextduedate) . '" style="width: 120px"> <input type="text" class="form-control" name="nextduetime" value="' . safe($nextduetime) . '" style="width: 80px"></div></td></tr>';
					if ($service['donotsuspenduntil'] > time()) {
						$donotsuspenddate = date('Y-m-d', $service['donotsuspenduntil']);
						$donotsuspendtime = date('H:i', $service['donotsuspenduntil']);
					} else {
						$donotsuspenddate = '';
						$donotsuspendtime = date('H:i', $service['nextduedate']);
					}
					echo '<tr><td>Override suspension and termination until:</td><td><div class="input-group" style="width: 200px"><input type="text" class="form-control" id="donotsuspenddate" name="donotsuspenddate" value="' . safe($donotsuspenddate) . '" style="width:120px"> <input type="text" class="form-control" name="donotsuspendtime" value="' . safe($donotsuspendtime) . '" style="width: 80px"></div></td></tr>';
					echo '</table><br>';
					echo '<table class="table table-striped">';
					echo '<tr><th colspan="3">Coupon</th></tr>';
					echo '<tr><td style="width:200px">Coupon Name</td><td>' . safe($service['coupon_name']) . '</td></tr>';
					if (!empty($service['coupon_name'])) {
						echo '<tr><td>Discount</td><td><input type="text" class="form-control" name="coupon_recurring" value="' . safe($coupon_data['recurring']) . '" style="width:100px"><select name="coupon_recurring_type" class="form-control"><option value="percent"' . ($coupon_data['recurring_type'] == 'percent' ? ' selected' : '') . '>percent</option><option value="fixed"' . ($coupon_data['recurring_type'] == 'fixed' ? ' selected' : '') . '>' . get_config('billic_currency_code') . '</option></select></td></tr>';
						echo '<tr><td>Remaining Billing Cycles</td><td><input type="text" class="form-control" name="remaining_billing_cycles" value="' . safe($coupon_data['remaining_billing_cycles']) . '" style="width:100px"></td></tr>';
					}
					echo '</table><br>';
					echo '<table class="table table-striped">';
					echo '<tr><th colspan="3">Edit Service Options</th></tr>';
					$options = $db->q('SELECT * FROM `serviceoptions` WHERE `serviceid` = ? ORDER BY `name`, `module_var`', $service['id']);
					if (empty($options)) {
						echo '<tr><td colspan="3">There are no options for this service.</td></tr>';
					}
					foreach ($options as $option) {
						echo '<tr><td>' . safe($option['name']) . '</td><td>' . safe($option['module_var']) . '</td><td style="width:70%"><input type="text" class="form-control" name="serviceoption_value[' . $option['id'] . ']" value="' . safe($option['value']) . '"></td></tr>';
					}
					echo '</table><br>';
					echo '<div align="center"><input type="submit" class="btn btn-success" name="update" value="Update Service &raquo;"></div>';
					echo '</form>';
					exit;
				}
			}
			if ($billic->user_has_permission($billic->user, 'Services_Change_Plan')) {
				if ($_GET['Do'] == 'ChangePlan') {
					if (isset($_POST['changeplan'])) {
						$plan = $db->q('SELECT * FROM `plans` WHERE `id` = ?', $_POST['newplan']);
						$plan = $plan[0];
						if (empty($plan)) {
							$billic->error('Invalid plan', 'newplan');
						}
						$orderform = $db->q('SELECT * FROM `orderforms` WHERE `id` = ?', $plan['orderform']);
						$orderform = $orderform[0];
						if (empty($orderform)) {
							$billic->error('The plan does not have an order form assigned', 'newplan');
						}
						$orderformitems = $db->q('SELECT * FROM `orderformitems` WHERE `parent` = ? ORDER BY `order` ASC', $plan['orderform']);
						if (empty($billic->errors)) {
							$db->q('DELETE FROM `serviceoptions` WHERE `serviceid` = ?', $service['id']);
							$serviceoptions = $orderformitems;
							$plan_vars = json_decode($plan['options'], true);
							if (is_array($plan_vars)) {
								foreach ($plan_vars as $module_var => $options) {
									if (empty($module_var)) {
										$module_var = $options['label'];
									}
									$found = false;
									foreach ($serviceoptions as $k => $v) {
										if ($v['module_var'] == $module_var) {
											$found = true;
											break;
										}
									}
									if ($found) {
										break;
									}
									$serviceoptions[] = array(
										'type' => 'planstaticvar',
										'name' => $options['label'],
										'module_var' => $module_var,
										'value' => $options['value'],
									);
								}
							}
							if (is_array($serviceoptions)) {
								foreach ($serviceoptions as $item) {
									if ($item['type'] == 'dropdown') {
										$orderformoption = $db->q('SELECT `name`, `module_var` FROM `orderformoptions` WHERE `parent` = ? ORDER BY `order` ASC LIMIT 1', $item['id']);
										$orderformoption = $orderformoption[0];
										$value = $orderformoption['module_var'];
										if (empty($value)) {
											$value = $orderformoption['name'];
										}
									} else if ($item['type'] == 'slider') {
										$value = $item['min'];
									} else if ($item['type'] == 'checkbox') {
										$value = 'No';
									} else if ($item['type'] == 'planstaticvar') {
										$value = $item['value'];
									} else {
										$value = $_POST[$item['id']];
									}
									echo $item['module_var'] . ' >> ' . $value . '<br>';
									$db->insert('serviceoptions', array(
										'serviceid' => $service['id'],
										'name' => $item['name'],
										'module_var' => $item['module_var'],
										'value' => $value,
									));
								}
							}
							$db->q('UPDATE `services` SET `packageid` = ?, `plan` = ?, `module` = ?, `tax_group` = ? WHERE `id` = ?', $plan['id'], $plan['name'], $orderform['module'], $plan['tax_group'], $service['id']);
							$billic->status = 'updated';
							$service = $db->q('SELECT * FROM `services` WHERE `id` = ?', $_GET['ID']);
							$service = $service[0];
						}
					}
					$billic->show_errors();
					$plan = $db->q('SELECT * FROM `plans` WHERE `id` = ?', $service['packageid']);
					$plan = $plan[0];
					echo '<form method="POST"><table class="table table-striped">';
					echo '<tr><th colspan="2">Change Plan</th></tr>';
					if (strlen($service['packageid']) == 128) {
						$exported = true;
						$plan = $db->q('SELECT `data` FROM `exported_plans` WHERE `hash` = ?', $service['packageid']);
						$plan = $plan[0];
						$plan = json_decode(trim($plan['data']) , true);
						$current_plan_name = $plan['name'];
					} else {
						$exported = false;
						$current_plan_name = $service['plan'];
					}
					echo '<tr><td width="130">Current Plan:</td><td>' . safe($current_plan_name) . '</td></tr>';
					echo '<tr><td' . $billic->highlight('newplan') . '>New Plan:</td><td><select class="form-control" name="newplan">';
					$plans = $db->q('SELECT * FROM `plans`', $service['packageid']);
					foreach ($plans as $plan) {
						echo '<option value="' . $plan['id'] . '"' . ($plan['id'] == $service['packageid'] ? ' selected' : '') . '>' . $plan['name'] . '</option>';
					}
					echo '</select></td></tr>';
					echo '<tr><td></td><td><input type="submit" class="btn btn-success" name="changeplan" value="Change Plan &raquo;"></td></tr>';
					echo '</table></form>';
					exit;
				}
			}
			if ($billic->user_has_permission($billic->user, 'Services_Move_Service')) {
				if ($_GET['Do'] == 'MoveService') {
					if (isset($_POST['moveservice'])) {
						$user = $db->q('SELECT * FROM `users` WHERE `id` = ?', $_POST['userid']);
						$user = $user[0];
						if (empty($user)) {
							$billic->error('User does not exist', 'userid');
						}
						if (empty($billic->errors)) {
							$db->q('UPDATE `services` SET `userid` = ?, `invoicegenerated` = \'0\', `reminderemailsent` = \'0\' WHERE `id` = ?', $_POST['userid'], $service['id']);
							echo 'The Service was successfully moved to User ID #' . $_POST['userid'];
							exit;
						}
					}
					$billic->show_errors();
					echo '<form method="POST"><table class="table table-striped">';
					echo '<tr><th colspan="2">Move Service to a different User</th></tr>';
					echo '<tr><td width="130">New User ID:</td><td><input type="text" class="form-control" name="userid" value="' . safe($_POST['userid']) . '"></td></tr>';
					echo '<tr><td></td><td><input type="submit" class="btn btn-default" name="moveservice" value="Move Service &raquo;"></td></tr>';
					echo '</table></form>';
					exit;
				}
			}
			if ($billic->user_has_permission($billic->user, 'Services_Create')) {
				if ($_GET['Do'] == 'Create') {
					if ($service['domainstatus'] != 'Pending' && $service['domainstatus'] != 'Terminated') {
						err('You can only create a service if the status is Pending or Terminated');
					}
					$import_data = json_decode(trim($service['import_data']) , true);
					if (!empty($import_data['serviceid'])) {
						err('This service already has an importe service ID. You are attempting to create a service at the remote billic which has already been created?');
					}
					$create = $this->service_create($service);
					if ($create !== true) {
						err('Create Error: ' . $create);
					} else {
						$db->q('UPDATE `services` SET `domainstatus` = ? WHERE `id` = ?', 'Active', $service['id']);
						echo '<div class="alert alert-success" role="alert">Service successfully created.</div>';
					}
					exit;
				}
			}
			if ($billic->user_has_permission($billic->user, 'Services_Suspend')) {
				if ($_GET['Do'] == 'Suspend') {
					$vars = array();
					$tmp = $db->q('SELECT * FROM `serviceoptions` WHERE `serviceid` = ?', $service['id']);
					foreach ($tmp as $v) {
						$vars[$v['module_var']] = $v['value'];
					}
					$array = array(
						'service' => $service,
						'vars' => $vars,
					);
					$do = call_user_func(array(
						$billic->modules[$service['module']],
						'suspend'
					) , $array);
					if ($do !== true) {
						err('Suspend Error: ' . $do);
					} else {
						$db->q('UPDATE `services` SET `domainstatus` = ? WHERE `id` = ?', 'Suspended', $service['id']);
						echo '<div class="alert alert-success" role="alert">Service successfully suspended.</div>';
					}
					exit;
				}
			}
			if ($billic->user_has_permission($billic->user, 'Services_Unsuspend')) {
				if ($_GET['Do'] == 'Unsuspend') {
					$vars = array();
					$tmp = $db->q('SELECT * FROM `serviceoptions` WHERE `serviceid` = ?', $service['id']);
					foreach ($tmp as $v) {
						$vars[$v['module_var']] = $v['value'];
					}
					$array = array(
						'service' => $service,
						'vars' => $vars,
					);
					$do = call_user_func(array(
						$billic->modules[$service['module']],
						'unsuspend'
					) , $array);
					if ($do !== true) {
						err('Unsuspend Error: ' . $do);
					} else {
						$db->q('UPDATE `services` SET `domainstatus` = ? WHERE `id` = ?', 'Active', $service['id']);
						$extratext = '';
						if ($service['donotsuspenduntil'] < time()) {
							$db->q('UPDATE `services` SET `donotsuspenduntil` = ? WHERE `id` = ?', (time() + 86400) , $service['id']);
							$extratext = ' The "Override suspension/termination" time has been set to 24 hours from now.';
						}
						echo '<div class="alert alert-success" role="alert">Service successfully unsuspended.' . $extratext . '</div>';
					}
					exit;
				}
			}
			if ($billic->user_has_permission($billic->user, 'Services_Terminate')) {
				if ($_GET['Do'] == 'Terminate') {
					$vars = array();
					$tmp = $db->q('SELECT * FROM `serviceoptions` WHERE `serviceid` = ?', $service['id']);
					foreach ($tmp as $v) {
						$vars[$v['module_var']] = $v['value'];
					}
					$array = array(
						'service' => $service,
						'vars' => $vars,
					);
					$do = call_user_func(array(
						$billic->modules[$service['module']],
						'terminate'
					) , $array);
					if ($do !== true) {
						err('Termination Error: ' . $do);
					} else {
						$db->q('UPDATE `services` SET `domainstatus` = ? WHERE `id` = ?', 'Terminated', $service['id']);
						echo '<div class="alert alert-success" role="alert">Service successfully terminated.</div>';
					}
					exit;
				}
			}
			/*$options = $db->q('SELECT * FROM `serviceoptions` WHERE `serviceid` = ?', $service['id']);
			echo '<table>';
			foreach($options as $option) {
				echo '<tr><td>'.$option['name'].'</td><td>'.$option['value'].'</td></tr>';
			}
			echo '</table>';*/
			switch ($service['domainstatus']) {
				case 'Suspended':
					echo '<b>This service is currently suspended. Reason: ' . safe($service['suspension_reason']) . '</b><br><br>';
				break;
				case 'Terminated':
					err('This service is Terminated.');
				break;
				case 'Pending':
					err('This service is pending.');
				break;
			}
			$vars = array();
			$tmp = $db->q('SELECT * FROM `serviceoptions` WHERE `serviceid` = ?', $service['id']);
			foreach ($tmp as $v) {
				$vars[$v['module_var']] = $v['value'];
			}
			$array = array(
				'service' => $service,
				'vars' => $vars,
			);
			if (method_exists($billic->modules[$service['module']], 'user_cp')) {
				if (!$billic->user_has_permission($billic->user, 'Services_ControlPanel')) {
					err('You do not have permission to access the Control Panel');
				}
				call_user_func(array(
					$billic->modules[$service['module']],
					'user_cp'
				) , $array);
			} else {
				echo '<p>The module ' . $service['module'] . ' does not have a control panel. No controls will be shown to the user.</p>';
			}
			return;
		}
		$billic->module('ListManager');
		$billic->modules['ListManager']->configure(array(
			'search' => array(
				'id' => 'text',
				'username' => 'text',
				'desc' => 'text',
				'plan' => 'text',
				'price' => 'text',
				'status' => array(
					'(All)',
					'Active',
					'Cancelled',
					'Pending',
					'Suspended',
					'Terminated'
				) ,
			) ,
		));
		$where = '';
		$where_values = array();
		if (isset($_POST['search'])) {
			if (!empty($_POST['id'])) {
				$where.= '`id` = ? AND ';
				$where_values[] = $_POST['id'];
			}
			if (!empty($_POST['username'])) {
				$where.= '`username` LIKE ? AND ';
				$where_values[] = '%' . $_POST['username'] . '%';
			}
			if (!empty($_POST['desc'])) {
				$where.= '`domain` LIKE ? AND ';
				$where_values[] = '%' . $_POST['desc'] . '%';
			}
			if (!empty($_POST['plan'])) {
				$where.= '`plan` LIKE ? AND ';
				$where_values[] = '%' . $_POST['plan'] . '%';
			}
			if (!empty($_POST['price'])) {
				$where.= '`amount` = ? AND ';
				$where_values[] = $_POST['price'];
			}
			if (!empty($_POST['status']) && $_POST['status'] != '(All)') {
				$where.= '`domainstatus` LIKE ? AND ';
				$where_values[] = '%' . $_POST['status'] . '%';
			}
		}
		$where = substr($where, 0, -4);
		$func_array_select1 = array();
		$func_array_select1[] = '`services`' . (empty($where) ? '' : ' WHERE ' . $where);
		foreach ($where_values as $v) {
			$func_array_select1[] = $v;
		}
		$func_array_select2 = $func_array_select1;
		$func_array_select1[0] = 'SELECT COUNT(*) FROM ' . $func_array_select1[0];
		$total = call_user_func_array(array(
			$db,
			'q'
		) , $func_array_select1);
		$total = $total[0]['COUNT(*)'];
		$pagination = $billic->pagination(array(
			'total' => $total,
		));
		echo $pagination['menu'];
		$func_array_select2[0] = 'SELECT * FROM ' . $func_array_select2[0] . ' ORDER BY `regdate` DESC LIMIT ' . $pagination['start'] . ',' . $pagination['limit'];
		$services = call_user_func_array(array(
			$db,
			'q'
		) , $func_array_select2);
		$billic->set_title('Admin/Services');
		echo '<h1><i class="icon-tasks"></i> Services</h1>';
		$billic->show_errors();
		echo $billic->modules['ListManager']->search_box();
		echo '<div style="float: right;padding-right: 40px;">Showing ' . $pagination['start_text'] . ' to ' . $pagination['end_text'] . ' of ' . $total . ' Services</div>' . $billic->modules['ListManager']->search_link();
		echo '<table class="table table-striped"><tr><th>Service</th><th>Info</th><th>Plan</th><th>Module</th><th>Price</th><th>Next Due Date</th><th>Status</th></tr>';
		if (empty($services)) {
			echo '<tr><td colspan="20">No Services matching filter.</td></tr>';
		} else {
			$modules_with_cp = array();
			$modules_with_cp_tmp = $billic->module_list_function('user_cp');
			foreach ($modules_with_cp_tmp as $k => $v) {
				$modules_with_cp[] = $v['id'];
			}
			unset($modules_with_cp_tmp);
			$billic->module('BillingCycles');
			if (count($services) > 0) {
				$billingcycles = $billic->modules['BillingCycles']->list_billing_cycles();
			}
		}
		foreach ($services as $service) {
			// billing cycle multiplier
			$price = round($service['amount'] * $billingcycles[$service['billingcycle']]['multiplier'], 2);
			// billing cycle discount
			$billingcycle_discount = (($price / 100) * $billingcycles[$service['billingcycle']]['discount']);
			$price = number_format($price - $billingcycle_discount, 2);
			if (!empty($billingcycles[$service['billingcycle']]['displayname2'])) {
				$price.= get_config('billic_currency_suffix') . ' every ' . $billingcycles[$service['billingcycle']]['displayname2'];
			}
			if (strlen($service['packageid']) == 128) {
				$exported = true;
				$plan = $db->q('SELECT `data` FROM `exported_plans` WHERE `hash` = ?', $service['packageid']);
				$plan = $plan[0];
				$plan = json_decode(trim($plan['data']) , true);
				$current_plan_name = $plan['name'];
			} else {
				$exported = false;
				$plan = $db->q('SELECT `name` FROM `plans` WHERE `id` = ?', $service['packageid']);
				$current_plan_name = $plan[0]['name'];
			}
			echo '<tr><td>';
			$user = $db->q('SELECT `firstname`, `lastname`, `companyname` FROM `users` WHERE `id` = ?', $service['userid']);
			$user = $user[0];
			if (!empty($user['firstname'])) {
				echo 'User: <span>' . $user['firstname'] . ' ' . $user['lastname'] . ' ' . (empty($user['companyname']) ? '' : '(' . $user['companyname'] . ')') . '</span><br>';
			}
			if (!empty($service['username'])) {
				echo 'Username: <span>' . $service['username'] . '</span><br>';
			}
			if (!empty($service['domain'])) {
				echo 'Description: <span>' . $service['domain'] . '</span><br>';
			}
			if (in_array($service['module'], $modules_with_cp)) {
				echo '<a href="/Admin/Services/ID/' . $service['id'] . '/" class="btn btn-xs btn-default"><i class="icon-gears-setting"></i> Control Panel</a> ';
			}
			echo '<a href="/Admin/Services/ID/' . $service['id'] . '/Do/Edit/" class="btn btn-xs btn-default"><i class="icon-edit-write"></i> Edit</a></td><td>';
			$billic->module($service['module']);
			if (method_exists($billic->modules[$service['module']], 'service_info')) {
				$vars = array();
				$tmp = $db->q('SELECT * FROM `serviceoptions` WHERE `serviceid` = ?', $service['id']);
				foreach ($tmp as $v) {
					$vars[$v['module_var']] = $v['value'];
				}
				$array = array(
					'service' => $service,
					'vars' => $vars,
				);
				$info = call_user_func(array(
					$billic->modules[$service['module']],
					'service_info'
				) , $array);
				echo $info;
			} else {
				echo $service['info_cache'];
			}
			echo '</td><td>' . safe($current_plan_name) . ($exported ? ' [Exported]' : '') . '</td><td>' . $service['module'] . '</td><td>' . get_config('billic_currency_prefix') . $price . '</td><td>' . $billic->date_display($service['nextduedate']) . '</td><td>';
			switch ($service['domainstatus']) {
				case 'Active':
					$label = 'success';
				break;
				case 'Suspended':
					$label = 'warning';
				break;
				case 'Terminated':
					$label = 'danger';
				break;
				case 'Cancelled':
				default:
					$label = 'default';
				break;
			}
			echo '<span class="label label-' . $label . '">' . $service['domainstatus'] . '</span>';
			echo '</td></tr>';
		}
		echo '</table>';
	}
	function user_area() {
		global $billic, $db;
		$billic->force_login();
		if (isset($_GET['ID'])) {
			$service = $db->q('SELECT * FROM `services` WHERE `id` = ? AND `userid` = ?', $_GET['ID'], $billic->user['id']);
			$service = $service[0];
			if (empty($service)) {
				err("Service " . $_GET['ID'] . " does not exist");
			}
			$billic->set_title('Service ' . $service['username'] . (empty($service['username']) ? 'ID ' . $service['id'] : ''));
			echo '<h1><a href="/User/Services/ID/' . $service['id'] . '/">' . $billic->service_type($service) . '</a></h1>';
			switch ($service['domainstatus']) {
				case 'Suspended':
					$reason = trim($service['suspension_reason']);
					if (empty($reason)) {
						$reason = 'Please pay the outstanding invoice to get this service reactivated.';
					}
					err('This service is currently suspended.  Reason: ' . safe($reason));
				break;
				case 'Terminated':
					err('This service is Terminated.');
				break;
				case 'Pending':
					err('This service is awaiting payment. After you pay the invoice your service will be activated.');
				break;
			}
			$billic->module($service['module']);
			$vars = array();
			$tmp = $db->q('SELECT * FROM `serviceoptions` WHERE `serviceid` = ?', $service['id']);
			foreach ($tmp as $v) {
				$vars[$v['module_var']] = $v['value'];
			}
			$array = array(
				'service' => $service,
				'vars' => $vars,
			);
			if (method_exists($billic->modules[$service['module']], 'user_cp')) {
				call_user_func(array(
					$billic->modules[$service['module']],
					'user_cp'
				) , $array);
			} else {
				echo '<p>There are no controls available for this service.</p>';
			}
			return;
		}
		$billic->set_title('My Services');
		echo '<h1><i class="icon-tasks"></i> My Services</h1>';
		$modules_with_cp = array();
		$modules_with_cp_tmp = $billic->module_list_function('user_cp');
		foreach ($modules_with_cp_tmp as $k => $v) {
			$modules_with_cp[] = $v['id'];
		}
		unset($modules_with_cp_tmp);
		$services = $db->q('SELECT * FROM `services` WHERE `userid` = ? AND (`domainstatus` = \'Active\' OR `domainstatus` = \'Suspended\' OR `domainstatus` = \'Pending\') ORDER BY `module`, `domain` ASC', $billic->user['id']);
		$billic->module('BillingCycles');
		if (count($services) > 0) {
			$billingcycles = $billic->modules['BillingCycles']->list_billing_cycles();
		}
		if (empty($services)) {
			echo '<p>You have no services.</p>';
		} else {
			echo '<table class="table table-striped"><tr><th>Name</th><th>Username</th><th>Info</th><th>Plan</th><th>Price</th><th>Next Due Date</th><th>Status</th><th>Actions</th></tr>';
			foreach ($services as $service) {
				// billing cycle multiplier
				$price = round($service['amount'] * $billingcycles[$service['billingcycle']]['multiplier'], 2);
				// billing cycle discount
				$billingcycle_discount = (($price / 100) * $billingcycles[$service['billingcycle']]['discount']);
				$price = number_format($price - $billingcycle_discount, 2);
				if (!empty($billingcycles[$service['billingcycle']]['displayname2'])) {
					$price.= get_config('billic_currency_suffix') . ' every ' . $billingcycles[$service['billingcycle']]['displayname2'];
				}
				if (strlen($service['packageid']) == 128) {
					$exported = true;
					$plan = $db->q('SELECT `data` FROM `exported_plans` WHERE `hash` = ?', $service['packageid']);
					$plan = $plan[0];
					$plan = json_decode(trim($plan['data']) , true);
					$current_plan_name = $plan['name'];
				} else {
					$exported = false;
					$current_plan_name = $service['plan'];
				}
				echo '<tr><td>' . $service['domain'] . '</td><td>' . $service['username'] . '</td><td>';
				$billic->module($service['module']);
				if (method_exists($billic->modules[$service['module']], 'service_info')) {
					$vars = array();
					$tmp = $db->q('SELECT * FROM `serviceoptions` WHERE `serviceid` = ?', $service['id']);
					foreach ($tmp as $v) {
						$vars[$v['module_var']] = $v['value'];
					}
					$array = array(
						'service' => $service,
						'vars' => $vars,
					);
					$info = call_user_func(array(
						$billic->modules[$service['module']],
						'service_info'
					) , $array);
					echo $info;
				} else {
					echo $service['info_cache'];
				}
				echo '</td><td>' . safe($current_plan_name) . ($exported ? ' [Exported]' : '') . '</td><td>' . get_config('billic_currency_prefix') . $price . '</td><td>' . $billic->date_display($service['nextduedate']) . '</td><td>';
				switch ($service['domainstatus']) {
					case 'Active':
						$label = 'success';
					break;
					case 'Suspended':
						$label = 'warning';
					break;
					case 'Terminated':
						$label = 'danger';
					break;
					case 'Cancelled':
					default:
						$label = 'default';
					break;
				}
				echo '<span class="label label-' . $label . '">' . $service['domainstatus'] . '</span>';
				echo '</td>';
				echo '<td>';
				$links = array();
				if (in_array($service['module'], $modules_with_cp)) {
					$links[] = '<a href="/User/Services/ID/' . $service['id'] . '/" class="btn btn-primary"><i class="icon-gears-setting"></i> Control Panel</a><br><br>';
				}
				$links[] = '<a href="/User/Tickets/Service/' . $service['id'] . '/New/" class="btn btn-sm btn-default"><i class="icon-ticket"></i> Open Ticket</a><br><br>';
				$links[] = '<a href="/User/Invoices/Service/' . $service['id'] . '/" class="btn btn-sm btn-default"><i class="icon-tags"></i> View Invoices</a>';
				echo implode(' ', $links);
				echo '</td></tr>';
			}
			echo '</table>';
		}
	}
	function users_submodule($array) {
		global $billic, $db;
		echo '<table class="table table-striped"><tr><th>ID</th><th>Description</th><th>Username</th><th>Info</th><th>Plan</th><th>Module</th><th>Price</th><th>Next Due Date</th><th>Status</th><th>Actions</th></tr>';
		$services = $db->q('SELECT * FROM `services` WHERE `userid` = ? ORDER BY `id` DESC', $array['user']['id']);
		if (empty($services)) {
			echo '<tr><td colspan="20">User has no services</td></tr>';
		}
		$billic->module('BillingCycles');
		if (count($services) > 0) {
			$billingcycles = $billic->modules['BillingCycles']->list_billing_cycles();
		}
		foreach ($services as $service) {
			// billing cycle multiplier
			$price = round($service['amount'] * $billingcycles[$service['billingcycle']]['multiplier'], 2);
			// billing cycle discount
			$billingcycle_discount = (($price / 100) * $billingcycles[$service['billingcycle']]['discount']);
			$price = number_format($price - $billingcycle_discount, 2);
			if (!empty($billingcycles[$service['billingcycle']]['displayname2'])) {
				$price.= get_config('billic_currency_suffix') . ' every ' . $billingcycles[$service['billingcycle']]['displayname2'];
			}
			if (strlen($service['packageid']) == 128) {
				$exported = true;
				$plan = $db->q('SELECT `data` FROM `exported_plans` WHERE `hash` = ?', $service['packageid']);
				$plan = $plan[0];
				$plan = json_decode(trim($plan['data']) , true);
				$current_plan_name = $plan['name'];
			} else {
				$exported = false;
				$current_plan_name = $service['plan'];
			}
			echo '<tr><td>' . $service['id'] . '</td><td>' . safe($service['domain']) . '</td><td>' . safe($service['username']) . '</td><td>';
			$billic->module($service['module']);
			if (method_exists($billic->modules[$service['module']], 'service_info')) {
				$vars = array();
				$tmp = $db->q('SELECT * FROM `serviceoptions` WHERE `serviceid` = ?', $service['id']);
				foreach ($tmp as $v) {
					$vars[$v['module_var']] = $v['value'];
				}
				$array = array(
					'service' => $service,
					'vars' => $vars,
				);
				$info = call_user_func(array(
					$billic->modules[$service['module']],
					'service_info'
				) , $array);
				echo $info;
			} else {
				echo $service['info_cache'];
			}
			echo '</td><td>' . safe($current_plan_name) . ($exported ? ' [Exported]' : '') . '</td><td>' . $service['module'] . '</td><td>' . get_config('billic_currency_prefix') . $price . '</td><td>' . $billic->date_display($service['nextduedate']) . '</td><td>';
			switch ($service['domainstatus']) {
				case 'Active':
					$label = 'success';
				break;
				case 'Suspended':
					$label = 'warning';
				break;
				case 'Terminated':
					$label = 'danger';
				break;
				case 'Cancelled':
				default:
					$label = 'default';
				break;
			}
			echo '<span class="label label-' . $label . '">' . $service['domainstatus'] . '</span>';
			echo '</td><td>';
			$links = array();
			if ($billic->user_has_permission($billic->user, 'Services_ControlPanel')) {
				$links[] = '<a href="/Admin/Services/ID/' . $service['id'] . '/" class="btn btn-xs btn-default"><i class="icon-gears-setting"></i> Control Panel</a>';
			}
			if ($billic->user_has_permission($billic->user, 'Services_Edit')) {
				$links[] = '<a href="/Admin/Services/ID/' . $service['id'] . '/Do/Edit/" class="btn btn-xs btn-default"><i class="icon-edit-write"></i> Edit</a>';
			}
			echo implode(' ', $links);
			echo '</td></tr>';
		}
		echo '</table>';
	}
	function cron() {
		global $billic, $db;
		// delete users who have not activated within 3 hours
		$db->q('DELETE FROM `users` WHERE `status` = \'activation\' AND `datecreated` < \'' . (time() - 10800) . '\'');
		// delete unpaid pending services older than 5 days
		$services = $db->q('SELECT `id` FROM `services` WHERE `domainstatus` = \'Pending\' AND `nextduedate` < \'' . (time() - 432000) . '\'');
		foreach ($services as $service) {
			$items = $db->q('SELECT `invoiceid` FROM `invoiceitems` WHERE `relid` = ?', $service['id']);
			$item = $items[0];
			$invoices = $db->q('SELECT `status` FROM `invoices` WHERE `id` = ?', $item['invoiceid']);
			foreach ($invoices as $invoice) {
				if ($invoice['status'] == 'Unpaid') {
					//echo 'cancelled invoice '.$item['invoiceid'].'<br>';
					$db->q('UPDATE `invoices` SET `status` = \'Cancelled\' WHERE `id` = ?', $item['invoiceid']);
				}
			}
			$db->q('DELETE FROM `services` WHERE `id` = ?', $service['id']);
			$db->q('DELETE FROM `serviceoptions` WHERE `serviceid` = ?', $service['id']);
		}
		/*
			Send suspension warning email
		*/
		$services = $db->q('SELECT * FROM `services` WHERE `domainstatus` = \'Active\' AND `reminderemailsent` = \'0\' AND `nextduedate` < ?', (time() + 259200)); // 3 days
		foreach ($services as $service) {
			$user_row = $db->q('SELECT * FROM `users` WHERE `id` = ?', $service['userid']);
			$user_row = $user_row[0];
			if (empty($user_row)) {
				echo '<br><b>ERROR:</b> Unable to find a USER ID ' . $service['userid'] . ' for the service ID ' . $service['id'] . '<br>';
				continue;
			}
			// Get the invoice
			$item = $db->q('SELECT `id`, `invoiceid` FROM `invoiceitems` WHERE `relid` = ? ORDER BY `id` DESC', $service['id']);
			$item = $item[0];
			if (empty($item)) {
				echo 'Service ID "' . $service['id'] . '" does not have an invoiceitem' . PHP_EOL;
				continue;
			}
			// Get the invoice
			$invoice = $db->q('SELECT `id` FROM `invoices` WHERE `id` = ?', $item['invoiceid']);
			$invoice = $invoice[0];
			if (empty($invoice)) {
				echo 'The invoice "' . $item['invoiceid'] . '" for Service ID "' . $service['id'] . '" does not exist. (Invoice Item "' . $item['id'] . '")' . PHP_EOL;
				continue;
			}
			$db->q('UPDATE `services` SET `reminderemailsent` = \'1\' WHERE `id` = ?', $service['id']);
			$billic->email($user_row['email'], 'Payment for Invoice #' . $invoice['id'] . ' due in 3 days', 'Dear ' . $user_row['firstname'] . ' ' . $user_row['lastname'] . ',<br>This invoice is due within the next 3 days. Please pay this invoice before the ' . $billic->date_display($service['nextduedate']) . ' to prevent your service being suspended.<br><br><hr><br><a href="http' . (get_config('billic_ssl') == 1 ? 's' : '') . '://' . get_config('billic_domain') . '/User/Invoices/ID/' . $invoice['id'] . '/">http' . (get_config('billic_ssl') == 1 ? 's' : '') . '://' . get_config('billic_domain') . '/User/Invoices/ID/' . $invoice['id'] . '/</a>');
			//echo 'suspension warning email sent to '.$user_row['email'].'<br><br>';
			
		}
		/*
			Generate Renewal Invoices
		*/
		$services = $db->q('SELECT * FROM `services` WHERE (`domainstatus` = \'Active\' OR `domainstatus` = \'Suspended\') AND `invoicegenerated` = \'0\' AND `nextduedate` < \'' . (time() + 604800) . '\''); // 1 week
		$billic->module('BillingCycles');
		if (count($services) > 0) {
			$billingcycles = $billic->modules['BillingCycles']->list_billing_cycles();
		}
		foreach ($services as $service) {
			if (!array_key_exists($service['billingcycle'], $billingcycles)) {
				$db->q('UPDATE `services` SET `errorfunc` = ?, `error` = ? WHERE `id` = ?', 'invoice', 'Invalid Billing Cycle', $service['id']);
				continue;
			}
			if ($service['nextduedate'] < 1) {
				$db->q('UPDATE `services` SET `errorfunc` = ?, `error` = ? WHERE `id` = ?', 'invoice', 'Invalid Next Due Date', $service['id']);
				continue;
			}
			$user_row = $db->q('SELECT * FROM `users` WHERE `id` = ?', $service['userid']);
			$user_row = $user_row[0];
			if (empty($user_row)) {
				$db->q('UPDATE `services` SET `errorfunc` = ?, `error` = ? WHERE `id` = ?', 'invoice', 'User who owns the service does not exist', $service['id']);
				continue;
			}
			$billic->module('Invoices');
			$billic->modules['Invoices']->generate(array(
				'service' => $service,
				'user' => $user_row,
				'duedate' => $service['nextduedate'],
			));
		}
		$services = $db->q('SELECT * FROM `services` WHERE `domainstatus` = \'Pending\' AND `nextduedate` > ? AND `error` = ?', time() , '');
		foreach ($services as $service) {
			$create = $this->service_create($service);
			if ($create === true) {
				$db->q('UPDATE `services` SET `domainstatus` = ? WHERE `id` = ?', 'Active', $service['id']);
				$user_row = $db->q('SELECT * FROM `users` WHERE `id` = ?', $service['userid']);
				$user_row = $user_row[0];
				if (!empty($user_row['email'])) {
					$template_id = $db->q('SELECT `email_template_activated` FROM `plans` WHERE `id` = ?', $service['packageid']);
					$template_id = $template_id[0]['email_template_activated'];
					if (!is_int($template_id)) {
						$template_id = $db->q('SELECT `id` FROM `emailtemplates` WHERE `default` = ?', 'Service Activated');
						$template_id = $template_id[0]['id'];
					}
					$billic->module('EmailTemplates');
					$billic->modules['EmailTemplates']->send(array(
						'to' => $user_row['email'],
						'template_id' => $template_id,
						'vars' => array(
							'services' => $service,
							'users' => $user_row,
						) ,
					));
				}
			} else {
				$db->q('UPDATE `services` SET `errorfunc` = ?, `error` = ? WHERE `id` = ?', 'create', 'Module Error: ' . $create, $service['id']);
			}
		}
		// suspend overdue accounts
		$services = $db->q('SELECT * FROM `services` WHERE `domainstatus` = \'Active\' AND `nextduedate` < \'' . time() . '\' AND `donotsuspenduntil` < \'' . time() . '\' AND `error` = \'\'');
		foreach ($services as $service) {
			$user_row = $db->q('SELECT * FROM `users` WHERE `id` = ?', $service['userid']);
			$user_row = $user_row[0];
			if (empty($user_row)) {
				$db->q('UPDATE `services` SET `errorfunc` = ?, `error` = ? WHERE `id` = ?', 'suspend', 'User who owns the service does not exist', $service['id']);
				continue;
			}
			$billic->module($service['module']);
			if (!method_exists($billic->modules[$service['module']], 'suspend')) {
				$db->q('UPDATE `services` SET `errorfunc` = ?, `error` = ? WHERE `id` = ?', 'suspend', 'There is no suspend function for the module "' . $service['module'] . '"', $service['id']);
				continue;
			}
			$vars = array();
			$tmp = $db->q('SELECT * FROM `serviceoptions` WHERE `serviceid` = ?', $service['id']);
			foreach ($tmp as $v) {
				$vars[$v['module_var']] = $v['value'];
			}
			$array = array(
				'service' => $service,
				'vars' => $vars,
			);
			$suspend = call_user_func(array(
				$billic->modules[$service['module']],
				'suspend'
			) , $array);
			if ($suspend !== true) {
				$db->q('UPDATE `services` SET `errorfunc` = ?, `error` = ? WHERE `id` = ?', 'suspend', 'Module Error: ' . $suspend, $service['id']);
			} else {
				$db->q('UPDATE `services` SET `domainstatus` = \'Suspended\' WHERE `id` = ?', $service['id']);
				$template_id = $db->q('SELECT `email_template_suspended` FROM `plans` WHERE `id` = ?', $service['packageid']);
				$template_id = $template_id[0]['email_template_suspended'];
				if (!is_int($template_id)) {
					$template_id = $db->q('SELECT `id` FROM `emailtemplates` WHERE `default` = ?', 'Service Suspended');
					$template_id = $template_id[0]['id'];
				}
				$billic->module('EmailTemplates');
				$billic->modules['EmailTemplates']->send(array(
					'to' => $user_row['email'],
					'template_id' => $template_id,
					'vars' => array(
						'services' => $service,
						'users' => $user_row,
					) ,
				));
			}
		}
		// terminate suspended accounts 2 weeks after nextduedate
		$services = $db->q('SELECT * FROM `services` WHERE `domainstatus` = ? AND `nextduedate` < ? AND `error` = \'\' AND `donotsuspenduntil` < \'' . time() . '\'', 'Suspended', (time() - 1209600));
		foreach ($services as $service) {
			$user_row = $db->q('SELECT * FROM `users` WHERE `id` = ?', $service['userid']);
			$user_row = $user_row[0];
			if (empty($user_row)) {
				$db->q('UPDATE `services` SET `errorfunc` = ?, `error` = ? WHERE `id` = ?', 'terminate', 'User who owns the service does not exist', $service['id']);
				continue;
			}
			$billic->module($service['module']);
			if (!method_exists($billic->modules[$service['module']], 'terminate')) {
				$db->q('UPDATE `services` SET `errorfunc` = ?, `error` = ? WHERE `id` = ?', 'terminate', 'There is no terminate function for the module "' . $servicep['module'] . '"', $service['id']);
				continue;
			}
			$vars = array();
			$tmp = $db->q('SELECT * FROM `serviceoptions` WHERE `serviceid` = ?', $service['id']);
			foreach ($tmp as $v) {
				$vars[$v['module_var']] = $v['value'];
			}
			$array = array(
				'service' => $service,
				'vars' => $vars,
			);
			$terminate = call_user_func(array(
				$billic->modules[$service['module']],
				'terminate'
			) , $array);
			if ($terminate !== true) {
				$db->q('UPDATE `services` SET `errorfunc` = ?, `error` = ? WHERE `id` = ?', 'terminate', 'Module Error: ' . $terminate, $service['id']);
			} else {
				$db->q('UPDATE `services` SET `domainstatus` = ? WHERE `id` = ?', 'Terminated', $service['id']);
				$template_id = $db->q('SELECT `email_template_terminated` FROM `plans` WHERE `id` = ?', $service['packageid']);
				$template_id = $template_id[0]['email_template_terminated'];
				if (!is_int($template_id)) {
					$template_id = $db->q('SELECT `id` FROM `emailtemplates` WHERE `default` = ?', 'Service Terminated');
					$template_id = $template_id[0]['id'];
				}
				$billic->module('EmailTemplates');
				$billic->modules['EmailTemplates']->send(array(
					'to' => $user_row['email'],
					'template_id' => $template_id,
					'vars' => array(
						'services' => $service,
						'users' => $user_row,
					) ,
				));
			}
		}
	}
	function service_create($service) {
		global $billic, $db;
		if (strlen($service['packageid']) == 128) {
			$exported = true;
			$plan = $db->q('SELECT `data` FROM `exported_plans` WHERE `hash` = ?', $service['packageid']);
			$plan = $plan[0];
			$plan = json_decode(trim($plan['data']) , true);
			$orderform = $plan['orderform'];
		} else {
			$exported = false;
			$plan = $db->q('SELECT * FROM `plans` WHERE `id` = ?', $service['packageid']);
			$plan = $plan[0];
			$orderform = $db->q('SELECT * FROM `orderforms` WHERE `id` = ?', $plan['orderform']);
			$orderform = $orderform[0];
		}
		if (empty($plan)) {
			return 'Invalid billic Plan';
		}
		if (empty($orderform)) {
			return 'Invalid billic Order Form';
		}
		$user_row = $db->q('SELECT * FROM `users` WHERE `id` = ?', $service['userid']);
		$user_row = $user_row[0];
		$billic->module($orderform['module']);
		if (!method_exists($billic->modules[$service['module']], 'create')) {
			return 'There is no create function for the module "' . $service['module'] . '"';
		}
		/*
		Build $vars to pass to the module
		*/
		$module_vars = array();
		$options = $db->q('SELECT `module_var`, `value` FROM `serviceoptions` WHERE `serviceid` = ?', $service['id']);
		foreach ($options as $option) {
			$module_vars[$option['module_var']] = $option['value'];
		}
		$array = array(
			'vars' => $module_vars,
			'service' => $service,
			'plan' => $plan,
			'user' => $user_row,
		);
		$create = call_user_func(array(
			$billic->modules[$service['module']],
			'create'
		) , $array);
		return $create;
	}
	function api() {
		global $billic, $db;
		$billic->force_login();
		if ($_POST['request'] == 'call_user_cp') {
			$service = $db->q('SELECT * FROM `services` WHERE `id` = ? AND `userid` = ?', $_POST['serviceid'], $billic->user['id']);
			$service = $service[0];
			if (empty($service)) {
				echo json_encode(array(
					'error' => 'Service ID ' . $_POST['serviceid'] . ' does not exist'
				));
				return;
			}
			$post = json_decode($_POST['post'], true);
			if (is_array($post)) {
				foreach ($post as $k => $v) {
					if (!array_key_exists($k, $_POST)) { // security - prevents end users from injecting forced params
						$_POST[$k] = $v;
					}
				}
			}
			$get = json_decode($_POST['get'], true);
			if (is_array($get)) {
				foreach ($get as $k => $v) {
					if (!array_key_exists($k, $_GET)) { // security - prevents end users from injecting forced params
						$_GET[$k] = $v;
					}
				}
			}
			switch ($service['domainstatus']) {
				case 'Suspended':
					echo json_encode(array(
						'error' => 'This service is Suspended at the remote billic'
					));
					return;
				break;
				case 'Terminated':
					echo json_encode(array(
						'error' => 'This service is Terminated at the remote billic'
					));
					return;
				break;
				case 'Pending':
					echo json_encode(array(
						'error' => 'This service is Pending at the remote billic'
					));
					return;
				break;
			}
			$billic->module($service['module']);
			$vars = array();
			$tmp = $db->q('SELECT * FROM `serviceoptions` WHERE `serviceid` = ?', $service['id']);
			foreach ($tmp as $v) {
				$vars[$v['module_var']] = $v['value'];
			}
			$array = array(
				'service' => $service,
				'vars' => $vars,
			);
			if (method_exists($billic->modules[$service['module']], 'user_cp')) {
				ob_start();
				define('SHUTDOWN_API_RETURN_HTML', true);
				call_user_func(array(
					$billic->modules[$service['module']],
					'user_cp'
				) , $array);
				return;
			} else {
				echo json_encode(array(
					'error' => 'There is no control panel for this service at the remote billic'
				));
				return;
			}
			return;
		}
		if ($_POST['request'] == 'renew_service') {
			$service = $db->q('SELECT * FROM `services` WHERE `id` = ? AND `userid` = ?', $_POST['serviceid'], $billic->user['id']);
			$service = $service[0];
			if (empty($service)) {
				echo json_encode(array(
					'error' => 'Service ID ' . $_POST['serviceid'] . ' does not exist'
				));
				return;
			}
			$billic->module('Invoices');
			// Get unpaid invoice for the service
			$invoiceid = $db->q('SELECT `invoiceid` FROM `invoiceitems` WHERE `relid` = ? ORDER BY `id` DESC LIMIT 1', $service['id']);
			$invoiceid = $invoiceid[0]['invoiceid'];
			$invoicecount = $db->q('SELECT COUNT(*) FROM `invoices` WHERE `id` = ? AND `status` = ?', $invoiceid, 'Unpaid');
			if ($invoicecount[0]['COUNT(*)'] == 0) {
				// Generate Invoice if not exists
				$invoiceid = $billic->modules['Invoices']->generate(array(
					'service' => $service,
					'user' => $billic->user,
					'duedate' => $service['nextduedate'],
				));
			}
			$invoice = $db->q('SELECT * FROM `invoices` WHERE `id` = ?', $invoiceid);
			$invoice = $invoice[0];
			if (empty($invoice)) {
				echo json_encode(array(
					'error' => 'Unable to get invoice from database'
				));
				return;
			}
			// Check Credit Balance
			if ($billic->user['credit'] <= 0 || $invoice['subtotal'] > $billic->user['credit']) {
				echo json_encode(array(
					'error' => 'Unable to pay invoice ' . $invoice['id'] . ' because you do not have enough account credit'
				));
				return;
			}
			ob_start();
			$error = $billic->modules['Invoices']->addpayment(array(
				'gateway' => 'credit',
				'invoiceid' => $invoice['id'],
				'amount' => $invoice['total'],
				'currency' => get_config('billic_currency_code') ,
				'transactionid' => 'credit',
			));
			ob_end_clean();
			if ($error !== true) {
				echo json_encode(array(
					'error' => 'Failed to apply credit to invoice ' . $invoice['id'] . ': ' . $error
				));
				return;
			}
			echo json_encode(array(
				'result' => 'ok'
			));
			return;
		}
		if ($_POST['request'] == 'sync_remote') {
			$service = $db->q('SELECT * FROM `services` WHERE `id` = ? AND `userid` = ?', $_POST['serviceid'], $billic->user['id']);
			$service = $service[0];
			if (empty($service)) {
				echo json_encode(array(
					'error' => 'Service ID ' . $_POST['serviceid'] . ' does not exist'
				));
				return;
			}
			if (!empty($_POST['desc'])) {
				$service['domain'] = $_POST['desc'];
			}
			$billic->module($service['module']);
			$info = '';
			if (method_exists($billic->modules[$service['module']], 'service_info')) {
				$vars = array();
				$tmp = $db->q('SELECT * FROM `serviceoptions` WHERE `serviceid` = ?', $service['id']);
				foreach ($tmp as $v) {
					$vars[$v['module_var']] = $v['value'];
				}
				$array = array(
					'service' => $service,
					'vars' => $vars,
				);
				$info = call_user_func(array(
					$billic->modules[$service['module']],
					'service_info'
				) , $array);
			}
			$db->q('UPDATE `services` SET `domain` = ?, `info_cache` = ?, `info_last_sync` = ? WHERE `id` = ?', $service['domain'], $info, time() , $service['id']);
			echo json_encode(array(
				'result' => 'ok',
				'info' => $info
			));
			return;
		}
	}
}
