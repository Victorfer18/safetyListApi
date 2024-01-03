<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class ClientController extends BaseController
{
    private $db;
    private $DATA_JWT;
    public function __construct()
    {
        $this->db = \Config\Database::connect();
        $this->DATA_JWT = json_decode(DATA_JWT);
    }

    public function getClientsByIdParent(int $id_parent)
    {
        $userID = $this->DATA_JWT->user_id;

        $query = $this->db->query("
        SELECT
            CLI.client_id,
            CLI.client_parent,
            CLI.client_created,
            CLITYP.client_type_name,
            CLITYP.client_type_image_path,
            INF.info_name,
            PAR.client_id AS parent_id
        FROM
            client AS CLI
        INNER JOIN
            info AS INF ON INF.client_id = CLI.client_id
        INNER JOIN
            client_type AS CLITYP ON CLITYP.client_type_id = CLI.client_type_id
        INNER JOIN
            situation AS SIT ON SIT.situation_id = CLI.situation_id
        LEFT JOIN
            client AS PAR ON PAR.client_id = CLI.client_parent AND PAR.client_level = 1
        LEFT JOIN
            organization AS ORG ON ORG.client_id = PAR.client_id
        LEFT JOIN
            ocupation AS OCU ON OCU.ocupation_id = INF.ocupation_id
        LEFT JOIN
            user_access UA ON CLI.client_id = UA.client_id
        LEFT JOIN
            (SELECT * FROM user WHERE user_id = $userID) U ON CLI.client_parent = U.client_id
        WHERE
            CLI.client_level = 2
            AND CLI.situation_id = 1
            AND (CASE U.group_id
                    WHEN 3 THEN CLI.client_id = UA.client_id AND UA.user_id = $userID AND UA.situation_id = 1
                    WHEN 2 THEN CLI.client_parent = U.client_id
                END)
            AND U.isSafetyList = 1
        GROUP BY
            CLI.client_id
        ORDER BY
            INF.info_name;
    ");

        $result = $query->getResultArray();

        $payload = array_map(function ($item) {
            return [
                'client_id' => $item['client_id'],
                'info_name' => $item['info_name'],
                'client_type_name' => $item['client_type_name'],
                'parent_id' => $item['parent_id'],
                'client_created' => $item['client_created'],
                'image' => fileToURL($item['client_type_image_path'], '/images'),
            ];
        }, $result);

        return $this->successResponse(INFO_SUCCESS, $payload);
    }

    public function getLogosInspectables(int $id_client)
    {
        $query = $this->db->table('organization_logo')
            ->select('organization_logo_path')
            ->where('client_id', $id_client)
            ->where('situation_id', 1)
            ->get()
            ->getResultArray();

        if (empty($query)) {
            return $this->errorResponse(ERROR_SEARCH_NOT_FOUND);
        }

        $logo = $query[0];

        if (!file_exists($logo['organization_logo_path'] ?? "")) {
            return;
        }
        $data = file_get_contents($logo['organization_logo_path']);
        $this->response->setContentType('image/jpeg');
        $this->response->setHeader('Content-Length', strlen($data));
        $this->response->setBody($data);
        return $this->response;
    }
}
