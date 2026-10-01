<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Models\mst_sekolah;
use App\Models\scctcust;
use App\Models\sholat_user;
use App\Models\ValidationMessage;
use App\Support\FilterHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DataSiswaController extends Controller
{
    private string $title = "Master Data";
    private string $mainTitle = "Data Siswa";
    private string $dataTitle = "Data Siswa";
    private ?string $unitScope = null;

    private array $allowedFilters = [
        "kelas" => "scctcust.DESC02",
        "sekolah" => "scctcust.CODE01",
        "siswa" => "scctcust.nmcust",
        "angkatan" => "scctcust.DESC04",
        "ayah" => "scctcust.GENUS",
    ];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (Auth::check()) {
                $user = Auth::user();
                $this->unitScope = $user->unit;
            }

            return $next($request);
        });
    }

    public function index()
    {
        $data["title"] = $this->title;
        $data["mainTitle"] = $this->mainTitle;
        $data["dataTitle"] = $this->dataTitle;
        $data["columnsUrl"] = route("admin.master-data.data-siswa.get-column");
        $data["datasUrl"] = route("admin.master-data.data-siswa.get-data");

        $data["thn_aka"] = scctcust::query()
            ->whereNotNull("DESC04")
            ->where("DESC04", "!=", "")
            ->when($this->unitScope, fn ($q) => $q->where("CODE02", $this->unitScope))
            ->select("DESC04 as thn_aka")
            ->distinct()
            ->orderBy("DESC04", "desc")
            ->get();

        $data["sekolah"] = mst_sekolah::when($this->unitScope, function ($query) {
            $query->where(function ($q) {
                $q->where("CODE01", $this->unitScope)
                    ->orWhere("DESC01", $this->unitScope);
            });
        })->get();

        $data["kelas"] = \App\Models\mst_kelas::query()
            ->when($this->unitScope, fn ($q) => $q->where("unit", $this->unitScope))
            ->orderBy("unit")
            ->orderBy("jenjang")
            ->orderBy("kelas")
            ->get();

        return view("admin.master_data.data_siswa.index", $data);
    }

    public function getColumn()
    {
        return [
            ["data" => null, "name" => "no", "className" => "text-center", "columnType" => "row", "exportable" => true],
            ["data" => "nocust", "name" => "NIS", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "va_spp", "name" => "VA SPP", "searchable" => false, "orderable" => false, "exportable" => true],
            ["data" => "va_saku", "name" => "VA Saku", "searchable" => false, "orderable" => false, "exportable" => true],
            ["data" => "NUM2ND", "name" => "No Pendaftaran", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "nmcust", "name" => "NAMA", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "CODE02", "name" => "Unit", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "DESC02", "name" => "Kelas", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "DESC03", "name" => "Jenjang", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "DESC04", "name" => "Angkatan", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "CODE04", "name" => "Gender", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "DESC05", "name" => "Alamat", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "GENUS", "name" => "Orang Tua", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "NO_WA", "name" => "No WA", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "STCUST", "name" => "Status (1/0)", "searchable" => true, "orderable" => true, "exportable" => true],
            [
                "data" => "edit_siswa",
                "name" => "Edit Data",
                "dataVal" => false,
                "columnType" => "button",
                "className" => "text-center",
                "button" => "modal",
                "buttonLink" => "#modal-edit-siswa",
                "buttonText" => "Edit",
                "buttonClass" => "btn btn-sm btn-info button-edit-siswa",
                "buttonIcon" => "ri-edit-line me-2",
            ],
            [
                "data" => "set_status",
                "name" => "Edit Status",
                "dataVal" => false,
                "columnType" => "button",
                "className" => "text-center",
                "button" => "modal",
                "buttonLink" => "#modal-edit-status-siswa",
                "buttonText" => "Edit Status",
                "buttonClass" => "btn btn-sm btn-warning button-set-status-siswa",
                "buttonIcon" => "ri-edit-box-line me-2",
            ],
        ];
    }

    public function getData(Request $request)
    {
        try {
            return $this->buildDataResponse($request);
        } catch (\Throwable $e) {
            report($e);
            \Illuminate\Support\Facades\Log::error('DataSiswa getData failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'draw' => (int) $request->get('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Gagal memuat data siswa: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function buildDataResponse(Request $request)
    {
        $draw = (int) $request->get("draw");
        $start = (int) $request->get("start", 0);
        $length = (int) $request->get("length", 10);

        $columnName_arr = $request->get("columns", []);
        $search_arr = $request->get("search", []);

        $defaultColumn = "scctcust.nocust";
        $defaultOrder = "asc";
        $columnName = $defaultColumn;
        $columnSortOrder = $defaultOrder;

        $hasNoWa = $this->scctcustHasColumn('NO_WA');
        $hasMusrifah = $this->scctcustHasColumn('musrifah');

        $sortable = [
            "nocust", "NUM2ND", "nmcust", "CODE02", "DESC02", "DESC03",
            "DESC04", "CODE04", "DESC05", "GENUS", "STCUST",
        ];
        if ($hasNoWa) {
            $sortable[] = "NO_WA";
        }

        $orderArr = $request->get("order");
        if (is_array($orderArr) && !empty($columnName_arr)) {
            $columnIndex = (int) ($orderArr[0]["column"] ?? 0);
            $columnSortOrder = $orderArr[0]["dir"] ?? $defaultOrder;
            $requestedColumn = $columnName_arr[$columnIndex]["data"] ?? null;
            if ($requestedColumn && in_array($requestedColumn, $sortable, true)) {
                $columnName = "scctcust." . $requestedColumn;
            }
        }

        $searchValue = $search_arr["value"] ?? "";

        $filters = [];
        $filter = FilterHandler::resolveFilters($request->input("filter"), $this->allowedFilters);
        if (!is_array($filter)) {
            $filter = [];
        }

        if ($this->unitScope !== null && $this->unitScope !== '') {
            $filter = array_merge($filter, [
                "scctcust.CODE02" => $this->unitScope,
            ]);
        }

        foreach ($filter as $key => $val) {
            switch ($key) {
                case "scctcust.DESC02":
                    $val = explode("~~", $val);
                    if (count($val) === 3) {
                        $filters[] = ["scctcust.CODE02", "=", $val[0]];
                        $filters[] = ["scctcust.DESC02", "=", $val[1]];
                        $filters[] = ["scctcust.DESC03", "=", $val[2]];
                    }
                    break;
                case "scctcust.CODE02":
                    $filters[] = ["scctcust.CODE02", "=", $val];
                    break;
                case "scctcust.nmcust":
                    if (is_numeric($val)) {
                        $filters[] = ["scctcust.nocust", "like", "%{$val}%"];
                    } else {
                        $filters[] = ["scctcust.nmcust", "like", "%{$val}%"];
                    }
                    break;
                case "scctcust.GENUS":
                    $filters[] = [$key, "like", "%{$val}%"];
                    break;
                default:
                    $filters[] = [$key, "=", $val];
                    break;
            }
        }

        $whereAny = [
            "scctcust.nmcust",
            "scctcust.nocust",
            "scctcust.NUM2ND",
            "scctcust.GENUS",
            "scctcust.CODE04",
            "scctcust.DESC05",
        ];
        if ($hasNoWa) {
            $whereAny[] = "scctcust.NO_WA";
        }
        if ($hasMusrifah) {
            $whereAny[] = "scctcust.musrifah";
        }

        $select = [
            "scctcust.CUSTID",
            "scctcust.nocust",
            "scctcust.NUM2ND",
            "scctcust.nmcust",
            "scctcust.CODE02",
            "scctcust.DESC02",
            "scctcust.DESC03",
            "scctcust.DESC04",
            "scctcust.CODE04",
            "scctcust.DESC05",
            "scctcust.GENUS",
            "scctcust.STCUST",
        ];
        if ($hasNoWa) {
            $select[] = "scctcust.NO_WA";
        }
        if ($hasMusrifah) {
            $select[] = "scctcust.musrifah";
        }

        $baseQuery = scctcust::query()
            ->when(!empty($filters), function ($q) use ($filters) {
                foreach ($filters as $f) {
                    $q->where($f[0], $f[1], $f[2]);
                }
            });

        $totalRecords = (clone $baseQuery)->count("CUSTID");

        $filteredQuery = (clone $baseQuery)->when($searchValue !== "", function ($q) use ($whereAny, $searchValue) {
            $sanitized = str_replace(["\\", "%", "_"], ["\\\\", "\\%", "\\_"], $searchValue);
            $q->where(function ($q2) use ($whereAny, $sanitized) {
                foreach ($whereAny as $column) {
                    $q2->orWhere($column, "like", "%{$sanitized}%");
                }
            });
        });

        $totalRecordsWithFilter = (clone $filteredQuery)->count("CUSTID");

        $records = $filteredQuery
            ->orderBy($columnName, $columnSortOrder)
            ->skip($start)
            ->take($length)
            ->select($select)
            ->get();

        $records = $records->map(function ($item) use ($hasNoWa, $hasMusrifah) {
            $row = $item->toArray();
            $nis = trim((string) ($item->nocust ?? ''));
            $musrifah = $hasMusrifah ? trim((string) ($item->musrifah ?? '')) : '';
            $row["item_id"] = $item->CUSTID;
            $row["nis"] = $item->nocust;
            $row["va_spp"] = ($nis !== '' && $nis !== '-')
                ? scctcust::showVASpp($nis)
                : '';
            $row["va_saku"] = ($nis !== '' && $nis !== '-')
                ? scctcust::showVASaku($nis)
                : '';
            $row["no_pendaftaran"] = $item->NUM2ND;
            $row["nama"] = $item->nmcust;
            $row["angkatan"] = $item->DESC04;
            $row["gender"] = $item->CODE04;
            $row["alamat"] = $item->DESC05;
            $row["ayah"] = $item->GENUS;
            $row["NO_WA"] = $hasNoWa ? ($item->NO_WA ?? '') : '';
            $row["no_wa"] = $row["NO_WA"];
            $row["musrifah"] = $musrifah;
            $row["musrifah_display"] = $musrifah;
            $row["edit_siswa"] = true;
            $row["set_status"] = true;
            unset($row["CUSTID"]);

            return $row;
        });

        return response()->json([
            "draw" => $draw,
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $totalRecordsWithFilter,
            "data" => $records,
        ]);
    }

    private function scctcustHasColumn(string $column): bool
    {
        static $cache = [];
        $key = strtolower($column);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $cache[$key] = \Illuminate\Support\Facades\Schema::connection('DATA_MYSQL')
                ->hasColumn('scctcust', $column);
        } catch (\Throwable) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }

    public function getSiswaSelect2(Request $request)
    {
        $term = trim((string) $request->get("term", ""));
        if ($term === "") {
            return response()->json([]);
        }

        $query = scctcust::query()->where("STCUST", 1)
            ->when($this->unitScope, fn ($q) => $q->where("CODE02", $this->unitScope));

        if (is_numeric($term)) {
            $query->where("nocust", "like", "%{$term}%");
        } else {
            $query->where("nmcust", "like", "%{$term}%");
        }

        $siswa = $query->orderBy("nmcust", "asc")
            ->limit(50)
            ->get(["CUSTID", "nocust", "nmcust", "CODE02", "DESC02", "DESC03", "DESC04"])
            ->map(function ($item) {
                return [
                    "id" => $item->CUSTID,
                    "text" => "NIS : {$item->nocust} - {$item->nmcust} | {$item->CODE02} - {$item->DESC02} - {$item->DESC03} - {$item->DESC04}",
                ];
            });

        return response()->json($siswa);
    }

    public function getMusrifahSelect2(Request $request)
    {
        $term = trim((string) ($request->get("term", $request->get("q", ""))));

        $query = sholat_user::query()->where("role", "Musrifah");

        if ($term !== "") {
            $sanitized = str_replace(["\\", "%", "_"], ["\\\\", "\\%", "\\_"], $term);
            $query->where(function ($q) use ($sanitized) {
                $q->where("nama", "like", "%{$sanitized}%")
                    ->orWhere("username", "like", "%{$sanitized}%");
            });
        }

        $items = $query->orderBy("username", "asc")
            ->limit(50)
            ->get(["username"])
            ->map(function ($item) {
                $username = trim((string) ($item->username ?? ""));

                return [
                    "id" => $username,
                    "text" => $username,
                ];
            })
            ->values();

        return response()->json($items);
    }

    public function getSiswa(Request $request)
    {
        $kelas = $request->kelas != "all" ? $request->kelas ?? null : null;
        $thn_aka = $request->angkatan != "all" ? $request->angkatan ?? null : null;
        $cariSiswa = $request->siswa;

        $nis = $nama = null;
        if (!empty($cariSiswa)) {
            if (is_numeric($cariSiswa)) {
                $nis = "%{$cariSiswa}%";
            } else {
                $nama = "%{$cariSiswa}%";
            }
        }

        if (!$nis && !$nama && !$kelas && !$thn_aka) {
            return response()->json([]);
        }

        $siswa = scctcust::query()
            ->when($this->unitScope, fn ($q) => $q->where("CODE02", $this->unitScope))
            ->when($kelas, fn ($q) => $q->where("CODE03", $kelas))
            ->when($thn_aka, fn ($q) => $q->where("DESC04", $thn_aka))
            ->when($nis, fn ($q) => $q->where("nocust", "like", $nis))
            ->when($nama, fn ($q) => $q->where("nmcust", "like", $nama))
            ->orderBy("nmcust", "asc")
            ->limit(200)
            ->get(["CUSTID", "nocust", "NUM2ND", "nmcust", "CODE02", "DESC02", "DESC03", "DESC04"])
            ->map(function ($item) {
                return [
                    "id" => $item->CUSTID,
                    "nis" => $item->nocust,
                    "no_daftar" => $item->NUM2ND,
                    "nama" => $item->nmcust,
                    "unit" => $item->CODE02,
                    "kelompok" => $item->DESC02,
                    "kelas" => trim(($item->DESC02 ?? "") . " " . ($item->DESC03 ?? "")),
                    "thn_aka" => $item->DESC04,
                ];
            });

        return response()->json($siswa);
    }

    public function ResetLoginAndroid($id, Request $request)
    {
        $siswa = scctcust::where("CUSTID", $id)->first();
        if (!$siswa) {
            return response()->json(["message" => "Siswa tidak ditemukan!"], 422);
        }
        if (!$siswa->nocust || $siswa->nocust === "-") {
            return response()->json(["message" => "Siswa tidak memiliki NIS!"], 422);
        }

        try {
            DB::beginTransaction();
            DB::select("CALL AndroidLogonFixer(?)", [$siswa->nocust]);
            DB::commit();

            return response()->json(["message" => "Login Android Direset!"], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(
                ["message" => "Gagal Mereset Login Android!", "error" => $e->getMessage()],
                422,
            );
        }
    }

    public function setStatusSiswa($id, Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            ["stcust" => ["required", "in:0,1"]],
            ValidationMessage::messages(),
            ValidationMessage::attributes(),
        );

        if ($validator->fails()) {
            return response()->json(
                ["message" => $validator->errors()->first(), "errors" => $validator->errors()],
                422,
            );
        }

        $siswa = scctcust::where("CUSTID", $id)->first();
        if (!$siswa) {
            return response()->json(["message" => "Siswa tidak ditemukan!"], 422);
        }

        try {
            DB::beginTransaction();
            $siswa->update(["STCUST" => (int) $request->stcust]);
            DB::commit();

            $statusString = ((int) $request->stcust === 1 ? "Aktif" : "NonAktif");

            return response()->json(
                ["message" => "Status siswa {$siswa->nmcust} menjadi {$statusString}!"],
                200,
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(
                ["message" => "Gagal Mengubah Status Siswa!", "error" => $e->getMessage()],
                422,
            );
        }
    }

    public function update(Request $request, string $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                "ayah" => ["nullable", "string", "max:255"],
                "alamat" => ["nullable", "string", "max:255"],
                "gender" => ["nullable", "string", "max:50"],
                "no_wa" => ["nullable", "string", "max:50"],
                "musrifah" => ["nullable", "string", "max:100"],
                "stcust" => ["nullable", "in:0,1"],
            ],
            ValidationMessage::messages(),
            array_merge(ValidationMessage::attributes(), [
                "musrifah" => "Musrifah",
            ]),
        );

        if ($validator->fails()) {
            return response()->json(
                ["message" => $validator->errors()->first(), "errors" => $validator->errors()],
                422,
            );
        }

        $musrifah = trim((string) $request->input("musrifah", ""));
        if ($musrifah !== "") {
            $exists = sholat_user::query()
                ->where("role", "Musrifah")
                ->where("username", $musrifah)
                ->exists();
            if (!$exists) {
                return response()->json(["message" => "Musrifah tidak ditemukan."], 422);
            }
        }

        $siswa = scctcust::when($this->unitScope, fn ($q) => $q->where("CODE02", $this->unitScope))
            ->where("CUSTID", $id)
            ->first();

        if (!$siswa) {
            return response()->json(["message" => "Siswa tidak ditemukan!"], 422);
        }

        try {
            DB::connection("DATA_MYSQL")->beginTransaction();
            $payload = [
                "GENUS" => $request->input("ayah") ?: null,
                "CODE04" => $request->input("gender") ?: null,
                "DESC05" => $request->input("alamat") ?: null,
                "NO_WA" => $request->input("no_wa") ?: null,
                "musrifah" => $musrifah !== "" ? $musrifah : null,
                "STCUST" => $request->filled("stcust")
                    ? (int) $request->input("stcust")
                    : (int) ($siswa->STCUST ?? 0),
                "LastUpdate" => date("Y-m-d H:i:s"),
            ];
            $siswa->update($payload);
            DB::connection("DATA_MYSQL")->commit();

            return response()->json(["message" => "Data siswa berhasil diperbarui."], 200);
        } catch (\Throwable $e) {
            DB::connection("DATA_MYSQL")->rollBack();

            return response()->json(
                ["message" => "Gagal memperbarui data siswa.", "error" => $e->getMessage()],
                422,
            );
        }
    }
}
