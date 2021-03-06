<?php

class Admin extends CI_Controller
{
	private $xavierRemoved;
	private $jesperRemoved;
	private $elliottRemoved;

	public function __construct()
	{
		parent::__construct();

		$this->user->requireOnline();

		if($this->user->getRank() < 2)
		{
			die('<script type="text/javascript">alert(\'Sorry, cool guys only\');window.location=\''.base_url().'\'</script>');
		}
		
		$this->load->model('product_model');
	}

	public function index()
	{
		$dailyIncome = $this->main_model->getAllDailyIncome();

		$last = 0;

		$graph = "";

		if($dailyIncome)
		{
			foreach($dailyIncome as $k => $day)
			{
				$last += $day['amount'];
				$dailyIncome[$k]['amount'] = $last;

				if(!$graph)
				{
					$graph = $dailyIncome[$k]['amount'];
				}
				else
				{
					$graph .= ",".$dailyIncome[$k]['amount'];
				}
			}
		}

		$v6 = $this->main_model->getIncome(3); // FusionCMS V6
		$v6_upgrade = $this->main_model->getIncome(4); // FusionCMS V6 upgrade

		$v6_preorders = $this->main_model->getIncome(8); // FusionCMS V6 release bundle
		$v6_promo = $this->main_model->getIncome(36); // FusionCMS V6 christmas promotion

		$v6_raxezwow = $this->main_model->getIncome(1); // RaxeZWoW theme
		$v6_blueweb = $this->main_model->getIncome(2); // BlueWeb theme

		$total = $v6 + $v6_upgrade + $v6_preorders + $v6_promo + $v6_raxezwow + $v6_blueweb;

		$failed_q = $this->db->query("SELECT paypal_logs.*, products.name FROM paypal_logs, products WHERE paypal_logs.validated=0 AND paypal_logs.product_id=products.id");
		
		if($failed_q->num_rows())
		{
			$failed = $failed_q->result_array();
		}
		else
		{
			$failed = false;
		}

		$data = array(
			'graph' => $graph,
			'graph_top' => $last,
			'last_date' => ($dailyIncome) ? $dailyIncome[count($dailyIncome)-1]['day'] : date("Y-m-d"),
			'total' => $total,
			'failed' => $failed
		);

		$output = $this->template->loadPage("admin", $data);
		
		$this->template->setTitle("Dashboard - ".$this->config->item('site-title'));
		$this->template->setHeadline("Hey there handsome!");
		$this->template->setBigHeader(false);
		$this->template->view($output);
	}

	public function live()
	{
		$output = $this->template->loadPage("live");
		
		$this->template->setTitle("Dashboard - ".$this->config->item('site-title'));
		$this->template->setHeadline('Watch the money flow!');
		$this->template->setBigHeader(false);
		$this->template->view($output);
	}
	
	public function pending()
	{
		$notValidatedThemes = $this->product_model->getNotValidated();
		
		$data = array(
			'pendings' => $notValidatedThemes
		);
		
		$output = $this->template->loadPage("pending", $data);
		
		$this->template->setTitle("Pending - ".$this->config->item('site-title'));
		$this->template->setHeadline('Pending products!');
		$this->template->setBigHeader(false);
		$this->template->view($output);
	}
	
	public function approve($id)
	{
		if($this->product_model->approve($id))
		{
			$product = $this->product_model->getProduct($id);

			$content = '<html>
						<body>
							<h1 style="color:#0099cc;font-weight:normal;font-size:16px;">Dear '.$product['real_name'].'...</h1>
							We are excited to tell you that your product with the name <b>'.$product['name'].'</b> has been approved and is now publicly exposed on the marketplace!<br /><br />Best regards,<br />the team
						</body>
					</html>';
		
			$this->mail($product['email'], "[".$this->config->item('site-title')."]".$product['name'], $this->config->item('sender_mail'), $content);

			redirect('admin/pending');
		}
	}

	public function deny($id)
	{
		if($this->product_model->deny($id))
		{
			$product = $this->product_model->getProduct($id);

			$content = '<html>
						<body>
							<h1 style="color:#0099cc;font-weight:normal;font-size:16px;">Dear '.$product['real_name'].'...</h1>
							We are sorry to tell you that your submitted product with the name <b>'.$product['name'].'</b> has been denied.<br /><br />Best regards,<br />the team
						</body>
					</html>';
		
			$this->mail($product['email'], "[".$this->config->item('site-title')."]".$product['name'], $this->config->item('sender_mail'), $content);

			redirect('admin/pending');
		}
	}

	private function mail($to, $subject, $from, $message)
	{
		$headers = "From: <" .$from. ">\r\n" .
					'Reply-To: ' .$from. "\r\n" .
					"Content-type: text/html\r\n"
					.'X-Mailer: PHP/' . phpversion();

		if(mail($to, $subject, $message, $headers))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function getTotal()
	{
		$v6 = $this->main_model->getIncome(3);
		$v6_upgrade = $this->main_model->getIncome(4);

		$v6_bundle = $this->main_model->getIncome(8);

		$v6_raxezwow = $this->main_model->getIncome(1);
		$v6_blueweb = $this->main_model->getIncome(2);

		$total = $v6 + $v6_upgrade + $v6_raxezwow + $v6_blueweb + $v6_bundle;

		die((string)$total);
	}

	public function complete($id)
	{
		$failed_q = $this->db->query("SELECT paypal_logs.*, products.name FROM paypal_logs, products WHERE paypal_logs.validated=0 AND paypal_logs.product_id=products.id");
		
		if($failed_q->num_rows())
		{
			$row = $failed_q->result_array();

			$this->userId = $row[0]['user_id'];
			$this->productId = $row[0]['product_id'];
			$this->payment_amount = $row[0]['payment_amount'];
			$this->txn_id = $row[0]['txn_id'];

			$this->processVerified();
		}

		$this->index();
	}

	private function processVerified()
	{
		if($this->productId == 8 && $this->config_paypal['launch'])
		{
			// Give access to item
			$order_data = array(
				"customer_id" => $this->userId,
				"product_id" => 3
			);

			$this->db->insert("orders", $order_data);

			// Give access to item
			$order_data = array(
				"customer_id" => $this->userId,
				"product_id" => 1
			);

			$this->db->insert("orders", $order_data);
		}
		else
		{
			// Give access to item
			$order_data = array(
				"customer_id" => $this->userId,
				"product_id" => $this->productId
			);

			$this->db->insert("orders", $order_data);
		}

		// Update the transaction log and set validated to 1
		$data = array("validated" => "1");
		$this->db->where('txn_id', $this->txn_id);
		$this->db->update('paypal_logs', $data);

		$this->db->query("UPDATE products SET downloads = downloads + 1 WHERE id=?", array($this->productId));

		$this->updateDailyIncome(); 
	}

	private function updateDailyIncome()
	{
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM daily_income WHERE day=?", array(date("Y-m-d")));

		$row = $query->result_array();

		if($row[0]['total'])
		{
			$this->db->query("UPDATE daily_income SET amount = amount + ".floor($this->payment_amount)." WHERE day=?", array(date("Y-m-d")));
		}
		else
		{
			$this->db->query("INSERT INTO daily_income(day, amount) VALUES(?, ?)", array(date("Y-m-d"), floor($this->payment_amount)));
		}
	}
}