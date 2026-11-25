<?php
// Jalankan dari terminal: php uji_crud.php
require_once __DIR__ . '/../config.php';
try {
 $pdo=db(); $pdo->beginTransaction();
 $pdo->exec("INSERT INTO pelanggan(nama,no_hp,alamat) VALUES('Uji CRUD Washie','089900000001','Alamat uji')");
 $idP=(int)$pdo->lastInsertId(); echo "CREATE pelanggan: berhasil (id=$idP)\n";
 $layanan=(int)$pdo->query("SELECT id_layanan FROM layanan WHERE kode='reguler'")->fetchColumn();
 $kategori=$pdo->query("SELECT id_kategori,tarif_dasar,satuan FROM kategori_barang WHERE kode='pakaian'")->fetch();
 $kode='TEST-'.date('YmdHis');
 $st=$pdo->prepare("INSERT INTO pesanan(kode_pesanan,id_pelanggan,id_layanan,metode_penyerahan,biaya_admin,biaya_laundry) VALUES(?,?,?,'mandiri',0,?)");
 $subtotal=(float)$kategori['tarif_dasar']*2; $st->execute([$kode,$idP,$layanan,$subtotal]); $id=(int)$pdo->lastInsertId();
 $st=$pdo->prepare("INSERT INTO detail_pesanan(id_pesanan,id_kategori,jumlah,satuan,tarif_satuan,subtotal_estimasi) VALUES(?,?,?,?,?,?)");
 $st->execute([$id,$kategori['id_kategori'],2,$kategori['satuan'],$kategori['tarif_dasar'],$subtotal]);
 echo "INSERT pesanan + detail: berhasil (id=$id, kode=$kode)\n";
 $st=$pdo->prepare("SELECT kode_pesanan,biaya_laundry FROM pesanan WHERE id_pesanan=?");$st->execute([$id]);$row=$st->fetch();
 echo "READ SELECT WHERE id_pesanan=$id: ".$row['kode_pesanan']." / Rp".number_format($row['biaya_laundry'],0,',','.')."\n";
 $st=$pdo->prepare("UPDATE pesanan SET status_pesanan='diproses' WHERE id_pesanan=?");$st->execute([$id]);
 $st=$pdo->prepare("SELECT status_pesanan FROM pesanan WHERE id_pesanan=?");$st->execute([$id]);
 echo "UPDATE WHERE id_pesanan=$id: ".$st->fetchColumn()."\n";
 $st=$pdo->prepare("DELETE FROM pesanan WHERE id_pesanan=?");$st->execute([$id]);
 $st=$pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE id_pesanan=?");$st->execute([$id]);
 echo "DELETE WHERE id_pesanan=$id: ".((int)$st->fetchColumn()===0?'berhasil':'gagal')." (detail ikut cascade)\n";
 $st=$pdo->prepare("DELETE FROM pelanggan WHERE id_pelanggan=?");$st->execute([$idP]);
 $pdo->commit(); echo "UJI CRUD SELESAI. Data uji dibersihkan.\n";
} catch(Throwable $e) {
 if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
 fwrite(STDERR,"UJI CRUD GAGAL: ".$e->getMessage()."\n"); exit(1);
}
