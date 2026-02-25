<!DOCTYPE html>
<html>

<head>
    <title>Create Announcement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <div class="container-fluid mt-lg-5 mt-sm-3">
        <div class="row">
            <div class="col-lg-4 col-md-4 col-sm-12">
                <div class="card shadow h-100">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">📢 สร้างข่าวประกาศ</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="create-announcement">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">ประเภทข่าว</label>
                                <select name="types" id="types" class="form-select">
                                    <option value="1">ข้อความ</option>
                                    <option value="2">รูปภาพ</option>
                                </select>
                            </div>
                            <div class="mb-3 types-2 d-none">
                                <label class="form-label">รูปภาพ</label>
                                <input type="file" name="image" id="image" class="form-control">
                            </div>
                            <!-- Title -->
                            <div class="mb-3 types-1">
                                <label class="form-label">หัวข้อข่าว</label>
                                <input type="text" name="title" id="title" class="form-control" required>
                            </div>

                            <!-- Message -->
                            <div class="mb-3 types-1">
                                <label class="form-label">รายละเอียด</label>
                                <textarea name="message" id="message" rows="4" class="form-control" required></textarea>
                            </div>

                            <!-- Send Time -->
                            <div class="mb-3">
                                <label class="form-label">ตั้งเวลาส่ง</label>
                                <div class="d-flex gap-2">
                                    <input type="date" name="send_at" class="form-control"  value="{{ date('Y-m-d') }}" required>
                                    <input type="time" name="send_at_time" class="form-control" value="{{ date('H:i') }}" required>
                                </div>
                            </div>

                            <!-- Buttons -->
                            <div class="d-flex justify-content-between">
                                <a href="/logout" class="btn btn-secondary">← ออก</a>
                                <button class="btn btn-success btn-create-announcement">
                                    🚀 สร้างข่าว
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8 col-md-8 col-sm-12">
                <div class="card shadow h-100">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">ประวัติข่าวประกาศ</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th width="5%">ลำดับ</th>
                                    <th width="15%">หัวข้อข่าว</th>
                                    <th>รายละเอียด</th>
                                    <th width="15%">ตั้งเวลาส่ง</th>
                                    <th width="10%">สถานะ</th>
                                    <th width="15%">กิจกรรม</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($announcements as $key => $announcement)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $announcement->type == 1 ? $announcement->title : '' }}</td>
                                    <td>
                                        @if($announcement->type == 1)
                                        {{ $announcement->message }}
                                        @else
                                        <img src="{{ config('filesystems.disks.s3.url') . '/' . $announcement->image }}" class="img-fluid" width="120">
                                        @endif
                                    </td>
                                    <td>{{ date('d/m/Y H:i', strtotime($announcement->send_at)) }}</td>
                                    <td>{{ $announcement->status }}</td>
                                    <td>
                                        @if($announcement->status == 'pending')
                                        <button class="btn btn-sm btn-outline-danger cancel-announcement" data-id="{{ $announcement->id }}">ยกเลิก</button>
                                        @endif

                                        @if($announcement->status != 'pending' && $announcement->status != 'sent')
                                        <button class="btn btn-sm btn-outline-success send-announcement" data-id="{{ $announcement->id }}">ส่งก่อนเวลา</button>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
<!-- asset('storage/'.$news->image) -->

</html>
<!-- script jquery and bootstrap -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        $('#types').change(function() {
            if ($(this).val() == '2') {
                $('.types-1').addClass('d-none');
                $('.types-2').removeClass('d-none');
                $('#title').prop('required', false);
                $('#message').prop('required', false);
                $('#image').prop('required', true);
            } else {
                $('.types-1').removeClass('d-none');
                $('.types-2').addClass('d-none');
                $('#title').prop('required', true);
                $('#message').prop('required', true);
                $('#image').prop('required', false);
            }
        });

        $('#create-announcement').submit(function(e) {
            e.preventDefault();
            $('.btn-create-announcement').prop('disabled', true);
            $('.btn-create-announcement').html('กำลังสร้าง...');
            let formData = new FormData(this);

            $.ajax({
                url: '/news/announcement',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    let data = response;
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert(data.message);
                    }
                    $('.btn-create-announcement').prop('disabled', false);
                    $('.btn-create-announcement').html('🚀 สร้างข่าว');
                },
                error: function(xhr) {
                    console.log(xhr.responseText);
                    $('.btn-create-announcement').prop('disabled', false);
                    $('.btn-create-announcement').html('🚀 สร้างข่าว');
                }
            });
        });

        $(document).on('click', '.cancel-announcement', function() {
            let id = $(this).data('id');
            if (confirm('ยกเลิกข่าวประกาศนี้หรือไม่?')) {
                cancelAnnouncement(id);
            }
        });
        $(document).on('click', '.send-announcement', function() {
            let id = $(this).data('id');
            if (confirm('ส่งข่าวประกาศนี้ตอนนี้หรือไม่?')) {
                sendAnnouncement(id);
            }
        });
        function cancelAnnouncement(id) {
            $.ajax({
                url: '/news/announcement/cancel/'+id,
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                },
                success: function(response) {
                    alert(response.message);
                    window.location.reload();
                }
            });
        }
        function sendAnnouncement(id) {
            $.ajax({
                url: '/news/announcement/send/'+id,
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                },
                success: function(response) {
                    alert(response.message);
                    window.location.reload();
                }
            });
        }
    });
</script>