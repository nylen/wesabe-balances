<?php

$time = microtime(true);

require_once 'settings.php';

$link = mysql_connect($db_server, $db_username, $db_password)
  or die(mysql_error());

mysql_select_db($db_database) or die(mysql_error());

$result = mysql_query(<<<SQL
  select a.name, a.type, a.id,
    coalesce(b.balance, i.avail_cash + p.market_value) balance,
    coalesce(b.updated_at, greatest(i.created_at, p.updated_at)) updated_at

  from accounts a

  left join (
    select b.*
    from account_balances b

    inner join (
      select account_id, max(updated_at) updated_at
      from account_balances
      group by 1
    ) m
    on m.account_id = b.account_id
    and m.updated_at = b.updated_at
  ) b
  on a.id = b.account_id

  left join (
    select b.*
    from investment_balances b

    inner join (
      select account_id, max(created_at) created_at
      from investment_balances
      group by 1
    ) m
    on m.account_id = b.account_id
    and m.created_at = b.created_at
  ) i
  on a.id = i.account_id

  left join (
    select p.account_id,
      sum(p.market_value) market_value,
      max(p.updated_at) updated_at
    from investment_positions p

    inner join (
      select account_id, investment_security_id, max(updated_at) updated_at
      from investment_positions
      group by 1,2
    ) m
    on m.account_id = p.account_id
    and m.investment_security_id = p.investment_security_id
    and m.updated_at = p.updated_at

    group by 1
  ) p
  on a.id = p.account_id

  order by a.type, a.name
SQL
  ) or die($mysql_error());
?>
<!DOCTYPE html>
<html>
  <head>
    <meta name="viewport" content="width=device-width" />
    <title>Account balances</title>
    <style type="text/css">
      body {
        font-family: Verdana, Arial, sans-serif;
      }
      .account {
        padding-bottom: 8px;
        margin-bottom: 8px;
        border-bottom: 2px solid #ccc;
      }
      .description {
        font-weight: bold;
        padding-right: 4px;
      }
    </style>
  </head>
  <body>
<?php

$account_types = array(
  'Account'           => 'Regular',
  'InvestmentAccount' => 'Investment'
  );

$first = true;
while($row = mysql_fetch_assoc($result)) {
  $type = $account_types[$row['type']];
  $balance = number_format($row['balance'], 2);
  $updated_at = date('n/d/Y g:i:s a', strtotime("$row[updated_at] UTC"));

  echo <<<HTML
    <div class="account">
      <table>
        <tr>
          <td class="description">Account:</td>
          <td class="content">$row[name]</td>
        </tr>
        <tr>
          <td class="description">Type:</td>
          <td class="content">$type</td>
        </tr>
        <tr>
          <td class="description">Balance:</td>
          <td class="content">\$$balance</td>
        </tr>
        <tr>
          <td class="description">As of:</td>
          <td class="content">$updated_at</td>
        </tr>
      </table>
    </div>

HTML;
}

$time = number_format(microtime(true) - $time, 4);
echo <<<HTML
    Page generated in ${time}s

HTML;
?>
  </body>
</html>
