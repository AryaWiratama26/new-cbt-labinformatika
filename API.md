# API Nilai — CBT Praktikum

Read-only JSON API untuk web kampus lain (mis. portal akademik) yang butuh data nilai.
Base URL production: `https://cbt.labinformatika.com/api/v1`

## Autentikasi

Semua endpoint butuh header:

```
X-API-Key: cbt_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Accept: application/json
```

Tanpa key → `401`, key salah → `401`, key dicabut/kedaluwarsa → `403`.
Rate limit: 60 request/menit per IP. Key dibuat admin di **Admin → API Keys**
(key plaintext hanya tampil sekali saat generate; hilang = generate baru + revoke yang lama).

## Endpoint

| Method & Path | Keterangan |
|---|---|
| `GET /courses` | Daftar mata kuliah (`id`, `code`, `name`) |
| `GET /classrooms` | Daftar kelas (`id`, `name`, `academic_year`, `semester`) |
| `GET /exams` | Daftar ujian. Default sembunyikan arsip; `?archived=1` hanya arsip, `?archived=all` semua. Filter: `?course_id=`, `?classroom_id=` |
| `GET /exams/{id}/grades` | Nilai per ujian. Filter: `?classroom_id=`, `?status=lulus\|gagal`. Paginasi 50 |
| `GET /classrooms/{id}/recap` | Matriks rekap: tiap mahasiswa + nilai per ujian + rata-rata |
| `GET /students/{nim}/grades` | Riwayat nilai satu mahasiswa (by NIM). 404 jika NIM tidak ada |

Status nilai: `lulus` (skor ≥ passing grade), `gagal`, `belum_mengerjakan` (sel kosong di rekap),
`is_remedial_attempt: true` untuk percobaan ke-2+. Waktu dalam ISO-8601.

## Contoh

```bash
curl -H "X-API-Key: cbt_live_XXXX" -H "Accept: application/json" \
  "https://cbt.labinformatika.com/api/v1/exams/3/grades?classroom_id=1"
```

```json
{
  "data": {
    "exam": { "id": 3, "title": "Ujian Praktikum Pemrograman Web", "passing_grade": 70 },
    "grades": [
      {
        "nim": "20241001",
        "nama": "Test Student",
        "kelas": "IF-1",
        "exam_id": 3,
        "attempt_number": 1,
        "score": 80,
        "status": "lulus",
        "is_remedial_attempt": false,
        "finished_at": "2026-11-10T09:12:44+07:00"
      }
    ]
  },
  "meta": { "current_page": 1, "per_page": 50, "total": 1, "last_page": 1 }
}
```

## Catatan

- Read-only: tidak ada endpoint tulis. Nilai hanya berubah lewat sistem ujian.
- API tidak terpengaruh toggle "Tampilkan Nilai ke Siswa" (toggle itu hanya untuk tampilan siswa; integrasi tetap menerima skor).
- Key bocor? Admin → API Keys → Cabut, generate baru. Tidak perlu ganti kode aplikasi.
