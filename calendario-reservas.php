<?php
function calendario_reservas_shortcode($atts) {
    // Recuperar os dados da tabela de reservas
    global $wpdb;
    $table_name_reservas = 'rgf_woocommerce_order_itemmeta'; // Nome da tabela de reservas (sem prefixo)
    $table_name_veiculos = 'rgf_woocommerce_order_items'; // Nome da tabela de veículos (sem prefixo)
    $table_name_status = 'rgf_wc_order_stats'; // Nome da tabela de status (sem prefixo)

    // Recuperar as reservas da tabela
    $dados_reservas = $wpdb->get_results("
        SELECT
            r.order_item_id,
            r.meta_key,
            r.meta_value,
            v.order_id,
            v.order_item_name
        FROM
            $table_name_reservas AS r
        JOIN
            $table_name_veiculos AS v ON r.order_item_id = v.order_item_id
        WHERE
            v.order_item_type = 'line_item'
    ");

    // Organizar os dados em um array
    $reservas = array();
    foreach ($dados_reservas as $reserva) {
        $order_item_id = $reserva->order_item_id;
        $meta_key = $reserva->meta_key;
        $meta_value = $reserva->meta_value;
        $order_id = $reserva->order_id;
        $order_item_name = $reserva->order_item_name;

        if (!isset($reservas[$order_item_id])) {
            $reservas[$order_item_id] = array(
                'order_id' => $order_id,
                'name' => $order_item_name,
                'start' => '',
                'end' => '',
            );
        }

        $reservas[$order_item_id][$meta_key] = $meta_value;
    }

    // Obter o status das reservas
    $order_ids = array_column($reservas, 'order_id');
    $status_reservas = $wpdb->get_results("
        SELECT
            order_id,
            status
        FROM
            $table_name_status
        WHERE
            order_id IN (" . implode(',', $order_ids) . ")
    ");

    // Mapear o status das reservas por order_id
    $status_map = array();
    foreach ($status_reservas as $status_reserva) {
        $status_map[$status_reserva->order_id] = $status_reserva->status;
    }

    // Definir os dias da semana
    $dias_semana = array('Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado');

    // Definir o número de dias a exibir no calendário
    $num_dias = 90;

    // Definir a data inicial como hoje
    $data_inicial = date('Y-m-d');

    // Gerar o código HTML do calendário em modo de tabela
    $html = '<div class="calendario-reservas">';
    $html .= '<div class="calendario-reservas-scroll">';
    $html .= '<table class="calendario-reservas-table table-fixada">';
    $html .= '<tr><th>Viatura/ID de Reserva</th>';

    for ($i = 0; $i < $num_dias; $i++) {
        $dia = date('d/m', strtotime("$data_inicial +$i days"));
        $dia_semana = $dias_semana[date('w', strtotime("$data_inicial +$i days"))];
        $html .= '<th><span class="dia-semana">' . $dia_semana . '</span><br>' . $dia . '</th>';
    }

    $html .= '</tr>';

foreach ($reservas as $reserva) {
    $vehicle_id = $reserva['order_id'];
    $vehicle_name = $reserva['name'];
    $start_date = date('Y-m-d', strtotime($reserva['start']));
    $end_date = date('Y-m-d', strtotime($reserva['end']));
    $status = $status_map[$vehicle_id];

    if ($status === 'wc-cancelled' || $status === 'wc-on-hold' || $status === 'wc-pending') {
        continue; // Ignorar reservas canceladas, em espera e pendentes
    }

	$html .= '<td><a href="' . admin_url('post.php?post=' . absint($vehicle_id) . '&action=edit') . '" target="_top">' . $vehicle_name . '<br> (ID: ' . $vehicle_id . ')</a></td>';


        for ($i = 0; $i < $num_dias; $i++) {
            $dia = date('Y-m-d', strtotime("$data_inicial +$i days"));

            $html .= '<td';

            if ($status === 'wc-processing' && ($dia >= $start_date && $dia <= $end_date)) {
                $html .= ' class="wc-processing"';
            } elseif ($status === 'wc-completed' && ($dia >= $start_date && $dia <= $end_date)) {
                $html .= ' class="wc-completed"';
            } elseif ($status === 'wc-on-hold' && ($dia >= $start_date && $dia <= $end_date)) {
                $html .= ' class="wc-on-hold"';
            }

            $html .= '>';

            if ($dia === $start_date && $dia === $end_date) {
                $html .= '<span class="recolha-entrega">';
                $html .= 'Entrega e Recolha<br><span class="hora-recolha-entrega">' . date('H:i', strtotime($reserva['start_time'])) . ' - ' . date('H:i', strtotime($reserva['end_time'])) . '</span>';
                $html .= '</span>';
            } elseif ($dia === $start_date) {
                $html .= '<span class="recolha">';
                $html .= 'Entrega<br><span class="hora-recolha">' . date('H:i', strtotime($reserva['start_time'])) . '</span>';
                $html .= '</span>';
            } elseif ($dia === $end_date) {
                $html .= '<span class="entrega">';
                $html .= 'Recolha<br><span class="hora-entrega">' . date('H:i', strtotime($reserva['end_time'])) . '</span>';
                $html .= '</span>';
            } elseif ($dia > $start_date && $dia < $end_date) {
                $html .= '<span class="reservado">Reservado</span>';
            } else {
                $html .= '-';
            }

            $html .= '</td>';
        }

        $html .= '</tr>';
    }

    $html .= '</table>';
    $html .= '</div>';
    $html .= '</div>';

    // Estilos CSS
    $html .= '<style>';
    $html .= '.calendario-reservas { overflow-x: auto; }';
    $html .= '.calendario-reservas-scroll { width: 100%; }';
    $html .= '.calendario-reservas-table { table-layout: fixed; width: ' . (($num_dias + 1) * 100) . 'px; }';
	$html .= '.view-order-btn { display: inline-block; margin-left: 5px; padding: 5px 10px; background-color: #ddd; color: #333; text-decoration: none; font-weight: bold; }';

	$html .= '.table-fixada thead th:first-child, .table-fixada tbody td:first-child {
    font-size: 12px;
    font-weight: bold;
    background-color: #ebebeb!important;
    padding: 10px 4px;
    border: 1px solid #dcdcdc;
    border-radius: 15px;
    color: #000;
	}
.recolha, .entrega, .recolha-entrega { color: #000; }
.wc-processing { background-color: #ffe4e4; }
.wc-completed { background-color: #a1f7b5; }
.reservado { color: #ffffff96; }
.dia-semana { font-weight: bold; }
.hora-recolha-entrega, .hora-recolha, .hora-entrega { font-size: 10px; }
.reservado, .recolha, .entrega, .recolha-entrega {
    padding: 5px;
}
th {
    font-size: 12px!important;
    border-bottom: 1px solid black;
}
th::first-line {
    font-size: 18px!important;
    font-weight: normal;
}
.recolha-entrega {
font-size: 10px;
}
.horas {
    font-size: 12px;
}
td {
    text-align: center;
}
/* CSS para fixar a coluna dos veículos */
.table-fixada thead th:first-child,
.table-fixada tbody td:first-child {
  position: sticky;
  left: 0;
  z-index: 2;
  background-color: #fff;
	color:#000!important;
}';
	$html .= '</style>';

// Scripts JavaScript

return $html;
}

add_shortcode('calendario_reservas', 'calendario_reservas_shortcode');
?>