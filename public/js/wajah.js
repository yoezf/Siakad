/* Absensi wajah di peramban: pencocokan dilakukan di perangkat, foto tidak dikirim ke server.
 * Butuh public/vendor/face-api/face-api.js (global faceapi) dan model di public/vendor/face-api/model. */
(function () {
    const MODEL = '/vendor/face-api/model';
    const AMBANG = 0.5;        // jarak terbesar yang dianggap wajah yang sama (makin kecil makin ketat)
    const SELISIH = 0.04;      // kandidat terbaik harus lebih dekat dari siswa lain sebesar ini
    const FRAME_PASTI = 3;     // berapa pembacaan berturut-turut harus mengarah ke siswa yang sama
    const EAR_TUTUP = 0.2, EAR_BUKA = 0.26; // rasio mata untuk kedipan
    const MASA_KEDIP = 5000;   // kedipan hanya berlaku 5 detik

    const jarak = (a, b) => { let s = 0; for (let i = 0; i < a.length; i++) { const d = a[i] - b[i]; s += d * d; } return Math.sqrt(s); };
    const ear = (p, i) => {
        const d = (a, b) => Math.hypot(p[a].x - p[b].x, p[a].y - p[b].y);
        return (d(i + 1, i + 5) + d(i + 2, i + 4)) / (2 * d(i, i + 3));
    };
    const tunggu = (ms) => new Promise((r) => setTimeout(r, ms));

    window.wajahApp = function (mode) {
        return {
            mode, kamera: false, memuat: false, pesan: 'Kamera belum dinyalakan.', galat: false,
            aktif: false, stream: null, modelSiap: false, data: [], sudah: {}, riwayat: [],
            kedip: { tutup: false, waktu: 0 }, kandidat: { id: null, n: 0 }, sibuk: false, jumlahData: 0,

            async siapkan() {
                if (this.modelSiap) return true;
                if (typeof faceapi === 'undefined') { this.tampil('Pustaka wajah tidak termuat. Muat ulang halaman.', true); return false; }
                this.memuat = true; this.tampil('Memuat model wajah (sekali saja, sekitar 7 MB)...');
                try {
                    await Promise.all([faceapi.nets.tinyFaceDetector.loadFromUri(MODEL), faceapi.nets.faceLandmark68Net.loadFromUri(MODEL), faceapi.nets.faceRecognitionNet.loadFromUri(MODEL)]);
                    // Pemanasan: panggilan pertama mengompilasi shader GPU dan bisa makan belasan detik; lakukan sekarang, bukan saat siswa sudah menghadap kamera.
                    this.tampil('Menyiapkan pengenalan wajah (pertama kali bisa sampai setengah menit)...');
                    const k = document.createElement('canvas'); k.width = k.height = 320;
                    await faceapi.detectAllFaces(k, new faceapi.TinyFaceDetectorOptions({ inputSize: 320 }));
                    await faceapi.nets.faceLandmark68Net.detectLandmarks(k);
                    await faceapi.nets.faceRecognitionNet.computeFaceDescriptor(k);
                    this.modelSiap = true;
                } catch (e) { this.tampil('Model wajah gagal dimuat.', true); return false; } finally { this.memuat = false; }
                return true;
            },

            async nyalakan() {
                if (!(await this.siapkan())) return false;
                if (this.stream) return true;
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    this.tampil('Kamera hanya bisa dipakai lewat HTTPS atau localhost. Buka alamat https dari jalankan-hp-aman.bat.', true); return false;
                }
                try {
                    this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }, audio: false });
                } catch (e) { this.tampil('Kamera tidak bisa dibuka. Izinkan akses kamera pada peramban.', true); return false; }
                const v = this.$refs.video; this.kamera = true; v.srcObject = this.stream; await v.play();
                return true;
            },

            matikan() {
                this.aktif = false;
                if (this.stream) { this.stream.getTracks().forEach((t) => t.stop()); this.stream = null; }
                this.kamera = false; this.tampil('Kamera dimatikan.');
            },

            tampil(teks, galat = false) { this.pesan = teks; this.galat = galat; },

            async deteksi() {
                const o = new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 });
                return faceapi.detectAllFaces(this.$refs.video, o).withFaceLandmarks().withFaceDescriptors();
            },

            // ---------- pendaftaran wajah ----------
            async daftar(siswaId, nama) {
                if (this.sibuk) return;
                this.sibuk = true;
                try {
                    if (!(await this.nyalakan())) return;
                    const vektor = []; let percobaan = 0;
                    while (vektor.length < 5 && percobaan < 40) {
                        percobaan++;
                        const h = await this.deteksi();
                        if (h.length !== 1) { this.tampil(h.length === 0 ? 'Wajah belum terlihat. Hadapkan wajah ke kamera.' : 'Harus satu wajah saja di depan kamera.'); await tunggu(400); continue; }
                        if (h[0].detection.score < 0.7) { this.tampil('Wajah kurang jelas. Cari tempat lebih terang.'); await tunggu(400); continue; }
                        vektor.push(Array.from(h[0].descriptor));
                        this.tampil(`${nama}: sampel ${vektor.length} dari 5. Gerakkan kepala sedikit antar sampel.`);
                        await tunggu(700);
                    }
                    if (vektor.length < 5) { this.tampil('Pengambilan wajah belum lengkap. Coba lagi dengan cahaya lebih baik.', true); return; }
                    const r = await this.$wire.simpanWajah(siswaId, vektor);
                    this.tampil(r && r.ok ? `Wajah ${nama} tersimpan.` : ((r && r.pesan) || 'Wajah tidak tersimpan.'), !(r && r.ok));
                } finally { this.sibuk = false; }
            },

            // ---------- pemindaian ----------
            async mulaiPindai(kelasId) {
                if (!kelasId) { this.tampil('Pilih kelas dulu.', true); return; }
                if (!(await this.nyalakan())) return;
                this.data = await this.$wire.dataKelas(kelasId);
                this.jumlahData = this.data.length; this.sudah = {}; this.kandidat = { id: null, n: 0 }; this.kedip = { tutup: false, waktu: 0 };
                if (!this.data.length) { this.tampil('Belum ada siswa di kelas ini yang didaftarkan wajahnya.', true); return; }
                this.aktif = true; this.tampil('Hadapkan wajah ke kamera, lalu berkedip.');
                while (this.aktif) {
                    try { await this.langkah(kelasId); } catch (e) { /* abaikan satu bingkai yang gagal */ }
                    await tunggu(200);
                }
            },

            cocokkan(desc) {
                const per = this.data.map((s) => ({ id: s.id, nama: s.nama, d: Math.min(...s.vektor.map((v) => jarak(desc, v))) })).sort((a, b) => a.d - b.d);
                const [a, b] = per;
                if (!a || a.d > AMBANG) return null;
                if (b && b.d - a.d < SELISIH) return null; // terlalu mirip dengan siswa lain: tidak dipastikan
                return a;
            },

            async langkah(kelasId) {
                const h = await this.deteksi();
                if (h.length === 0) { this.kandidat = { id: null, n: 0 }; this.tampil('Hadapkan wajah ke kamera, lalu berkedip.'); return; }
                if (h.length > 1) { this.kandidat = { id: null, n: 0 }; this.tampil('Satu orang saja di depan kamera.'); return; }
                const p = h[0].landmarks.positions, e = (ear(p, 36) + ear(p, 42)) / 2, kini = Date.now();
                if (e < EAR_TUTUP) this.kedip.tutup = true;
                else if (e > EAR_BUKA && this.kedip.tutup) { this.kedip = { tutup: false, waktu: kini }; }
                const m = this.cocokkan(h[0].descriptor);
                if (!m) { this.kandidat = { id: null, n: 0 }; this.tampil('Wajah belum dikenali. Dekatkan wajah dan pastikan cahaya cukup.'); return; }
                this.kandidat = this.kandidat.id === m.id ? { id: m.id, n: this.kandidat.n + 1 } : { id: m.id, n: 1 };
                if (this.sudah[m.id]) { this.tampil(`${m.nama} sudah tercatat.`); return; }
                const berkedip = kini - this.kedip.waktu < MASA_KEDIP;
                if (this.kandidat.n < FRAME_PASTI) { this.tampil('Memeriksa wajah...'); return; }
                if (!berkedip) { this.tampil('Berkedip sekali untuk memastikan Anda sungguhan.'); return; }
                this.sudah[m.id] = true; this.kedip = { tutup: false, waktu: 0 }; this.kandidat = { id: null, n: 0 };
                const r = await this.$wire.catatHadir(kelasId, m.id);
                const waktu = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                if (r && r.hasil === 'dicatat') { this.riwayat.unshift({ nama: m.nama, waktu, teks: 'Hadir tercatat' }); this.tampil(`${m.nama}: hadir tercatat.`); }
                else if (r && r.hasil === 'sudah') { this.riwayat.unshift({ nama: m.nama, waktu, teks: 'Sudah tercatat sebelumnya' }); this.tampil(`${m.nama} sudah tercatat sebelumnya.`); }
                else { delete this.sudah[m.id]; this.tampil((r && r.pesan) || 'Pencatatan gagal.', true); }
            },
        };
    };
})();
