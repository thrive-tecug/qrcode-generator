import os
import shutil
import zipfile
import threading
import time
import qrcode
import io
import base64
import re

ROOT = os.getcwd()
OUTPUT_FOLDER = os.path.join(ROOT, "OUTPUT") # folder where to put user's files
MAX_LOG_LINES = 100 # the maximum number of lines that can be logged
CSV_FILE = 'qrcodes.csv'
MAX_ZIP_THREADS=8

REQ_CANCEL, REQ_PAUSE, REQ_PROGRESS, REQ_ACTIVE, REQ_COMPLETE = "req.cancel", "req.pause", "req.progress", "req.active", "req.complete"
REC_CANCEL, REC_PAUSE, REC_PROGRESS, REC_ACTIVE, REC_COMPLETE = "rec.cancel", "rec.pause", "rec.progress", "rec.active", "rec.complete"

def hex_to_rgb(hex_string):
    try:
        if "#" in hex_string:
            hex_string = hex_string.replace("#", '')
        h = hex_string.lstrip()
        return tuple(int(h[i:i+2], 16) for i in (0, 2, 4))
    except:
        return None

def _zip_and_delete_folder(base_folder, target_folder_name):
    '''
    zips a file setting prefix before while handling and removing it after
    @params
    base_folder
        the folder where the folder to be zipped exists. Also, the folder where the result will be placed
    target_folder_name
        the folder inside base_folder that contains the files to be zipped
    '''
    try:
        incomplete_prefix="__"
        target_folder_path = os.path.join(base_folder, target_folder_name)
        file_list = [os.path.join(target_folder_path, fl) for fl in os.listdir(target_folder_path)]
        pending_zipfilename = os.path.join(base_folder, "{}{}.zip".format(incomplete_prefix, target_folder_name))
        final_zipfilename = os.path.join(base_folder, "{}.zip".format(target_folder_name))
        with zipfile.ZipFile(pending_zipfilename, 'w') as zip:
            for file in file_list:
                filename = os.path.basename(file)
                zip.write(file, compress_type=zipfile.ZIP_DEFLATED, arcname=filename)
        shutil.rmtree(target_folder_path)
        os.rename(pending_zipfilename, final_zipfilename)
    except Exception as e:
        # print(e)
        pass

def generate_qrcode(data, version=1, error_correction=qrcode.constants.ERROR_CORRECT_M, box_size=4, border=1, fgcolor=(0, 0, 0), bgcolor=(255, 255, 255)):
    """
    returns image of qrcode with data
    @params
    version : int; range(1, 40); default=1
        a determinant of size of qrcode generated (1 is the smallest size)
    error_correction : int ; constant
        error correction criteria
    box_size : int; default=4
        the size of the qrcode black boxes
    border : int
        this controls how many boxes thick the border should be
    """
    if error_correction not in [qrcode.constants.ERROR_CORRECT_M, qrcode.constants.ERROR_CORRECT_L, qrcode.constants.ERROR_CORRECT_H, qrcode.constants.ERROR_CORRECT_Q]:
        return None
    qr = qrcode.QRCode(
        version=version,
        error_correction=error_correction,
        box_size=box_size,
        border=border,
    )
    qr.add_data(data)
    qr.make(fit=True)
    img = qr.make_image(fill_color=fgcolor, back_color=bgcolor)
    return img

class QRCodeGenerator:
    def __init__(self, name=None, version=1, error_correction=qrcode.constants.ERROR_CORRECT_M, box_size=4, border=1, folder_batch=500, fgcolor=(0,0,0), bgcolor=(255,255,255), folder=None):
        """
        @params
        """
        self.version = version
        self.error_correction = error_correction
        self.box_size=box_size
        self.border = border
        self.error_message = None
        self.progress=0 # zero indexed value
        self.total=None
        self.cancelled=False
        self._zip_thread_data = dict()
        self._gen_zip_data = dict()
        self.name = name
        self.cb_thread_active=False
        self.callback=None
        self.cycle_completed=False
        self.folder_batch = folder_batch
        self.fgcolor = fgcolor
        self.bgcolor = bgcolor
        self.targetfolder=OUTPUT_FOLDER
        if folder is not None:
            self.targetfolder=os.path.join(self.targetfolder, folder)
        if not os.path.exists(self.targetfolder):
            os.makedirs(self.targetfolder)

    def delete_files(self):
        # removes target folder
        if os.path.exists(self.targetfolder):
            shutil.rmtree(self.targetfolder)

    def generate_qrcode(self, data):
        return generate_qrcode(data, self.version, self.error_correction, self.box_size, self.border, fgcolor=self.fgcolor, bgcolor=self.bgcolor)
    
    def __run_after_cycle(self):
        # takes in a call back that can be called every cycle and has access to object
        if self.callback is None:
            return
        if self.cb_thread_active:
            return
        self.cb_thread_active=True
        def function():
            try:
                self.callback(self)
            except Exception as e:
                pass
            self.cb_thread_active=False
        cb_thread = threading.Thread(target=function)
        cb_thread.start()
        return
    
    def register_callback(self, callback):
        self.callback=callback

    def deregister_callback(self):
        self.callback=None


    def generate_qrcodes(self, start_number, end_number, qr_serial_length, csv_serial_length, pre_string, pro_string, outfolder):
        """
        generates qrcodes and places them into specified folder
        start_number :
            the number to start at
        end_number :
            the number to end at; exclusive
        """
        csv_data = ""
        self.cycle_completed=False
        try:
            csv_path = os.path.join(outfolder, CSV_FILE)
            # do not remove folder if exists
            if not os.path.exists(outfolder):
                os.makedirs(outfolder)
                with open(csv_path, 'w') as fw:
                    fw.write("Serials,Filename\n")
            for i in range(start_number, end_number):
                if self.cancelled:
                    self.error_message="Cancelled by User"
                    return False
                qr_serial_fill = str(i).zfill(qr_serial_length)
                csv_serial_fill = str(i).zfill(csv_serial_length)
                imname = "qrcode{}.png".format(i)
                qrdata = "{}{}{}".format(pre_string, str(qr_serial_fill), pro_string)
                img = self.generate_qrcode(qrdata)
                outpath = os.path.join(outfolder, imname) # filename changes here
                img.save(outpath)
                csv_data += "{},{}\n".format(csv_serial_fill,imname)
                self.__run_after_cycle()
                if i%1000==0:
                    with open(csv_path, 'a') as fw:
                        fw.write(csv_data)
                    csv_data=""
                self.progress+=1
                if i==(end_number-1):
                    self.cycle_completed=True
            if len(csv_data) > 0:
                with open(csv_path, 'a') as fw:
                    fw.write(csv_data)
            return True
        except Exception as e:
            self.error_message = "{} : error@generate qrcodes : {}".format(self.name,e)
            # print(self.error_message)
            return False
    
    def generate(self, start_number, limit, qr_serial_length, csv_serial_length, pre_string, pro_string, progress=0, zip=True):
        """
        macro qrcode generator; 
        generates qrcodes and places them in numbered subfolders
        progress : int
            the value the program last fully generated; start at progress+1 to resume
        Note:
        this function's generations are threadable
        """
        im = start_number 
        self.total = limit
        end_number = start_number+limit
        rem = limit
        # detect starting incomplete batch
        if progress<limit:
            _im,_progress,_rem,_limit=start_number,0,limit,limit
            while _im<end_number:
                _next_limit=min(_rem, self.folder_batch)
                if(progress>=_progress and progress<(_progress+_next_limit)):
                    im = _im
                    self.progress=_progress
                    break
                _im+=_next_limit
                _progress+=_next_limit
                _rem = (_limit-_progress)
        else:
            self.progress=limit
            im=end_number
        self.progress=progress
        # run actual batch generation
        while im < end_number:
            foldername="{}_{}".format(im, im+self.folder_batch-1)
            outfolder = os.path.join(self.targetfolder, foldername) 
            next_limit = min(rem, self.folder_batch)
            curr_end = im+next_limit
            state = self.generate_qrcodes(
                start_number=max(self.progress,im), end_number=curr_end, qr_serial_length=qr_serial_length, csv_serial_length=csv_serial_length,
                pre_string=pre_string, pro_string=pro_string, outfolder=outfolder)
            # self.progress+=next_limit
            rem = limit-self.progress
            if not state:
                break
            # start zip thread here if the cycle completed
            if zip and self.cycle_completed:
                # if the zipping threads are many; wait
                while self.get_number_active_zip_threads() > MAX_ZIP_THREADS:
                    time.sleep(30)
                try:
                    thread = threading.Thread(target=self.zip_folder, args=(foldername,))
                    thread.start()
                except Exception as e:
                    self.error_message = "{} : Error @zip : {}".format(self.name, e)
            im+=self.folder_batch

    def get_number_active_zip_threads(self):
        return len(self._zip_thread_data)
    
    def zip_folder(self, foldername):
        """
        zips a folder and notifies when it is complete
        """
        self._zip_thread_data[foldername] = False
        _zip_and_delete_folder(base_folder=self.targetfolder, target_folder_name=foldername)
        del self._zip_thread_data[foldername]

    def cancel(self):
        self.cancelled=True
        
    def is_complete(self):
        return self.total==self.progress  # and zipping is done

def get_error_correction_level(error_correction):
    return eval("qrcode.constants.{}".format(error_correction))


# -------------------------------- SCRIPT HELPERS -------------------------------

def dict_from_param_string(param_string):
    start_index, end_index = None, None
    if '{' in param_string and param_string.count('{')==1:
        start_index = param_string.index('{')
    if '}' in param_string and param_string.count('}')==1:
        end_index = param_string.index('}')
    if start_index is None or end_index is None:
        return None
    params_part = param_string[start_index+1:end_index]
    pairs = params_part.split(',')
    res = dict()
    for pair in pairs:
        if ':' not in pair:
            return None
        kv = pair.split(':')
        if len(kv)!=2:
            return None
        k, v = kv
        res[k.strip()] = v.strip()
    return res

def generate_image_b64_preview(string,error_correction,box_size,border,fgcolor,bgcolor,version=1):
    # qrcode.constants.ERROR_CORRECT_M, (0,0,0)
    _error_correction = get_error_correction_level(error_correction)
    _box_size = int(box_size)
    _border = int(border)
    _fore_color, _back_color = hex_to_rgb(fgcolor), hex_to_rgb(bgcolor)
    if _fore_color is None or _back_color is None:
        return {"status":"failed", "message":"failed to convert color"}
    img = generate_qrcode(string,version,_error_correction,_box_size,_border,_fore_color,_back_color)
    if img is not None:
        b = io.BytesIO()
        img.save(b, "PNG")
        b.seek(0)
        obj = base64.b64encode(b.getvalue())
        return {"status":"success", "data":obj.decode('ascii')}
    return {"status":"failed", "message":"failed to generate"}

def send_message(message, path, data=None):
    base_folder = os.path.join(OUTPUT_FOLDER, path)
    base_folder = os.path.abspath(base_folder)
    if os.path.exists(base_folder):
        with open(os.path.join(base_folder, message), 'w') as fw:
            if data is not None:
                fw.write('{}'.format(data))

def delete_message(message, path):
    message_path = os.path.join(OUTPUT_FOLDER, path, message)
    message_path = os.path.abspath(message_path)
    if os.path.exists(message_path):
        os.remove(message_path)

def receive_message(message, path, delete=True):
    message_path = os.path.join(OUTPUT_FOLDER, path, message)
    message_path = os.path.abspath(message_path)
    if os.path.exists(message_path):
        content=None
        with open(message_path) as fr:
            content = fr.read()
        if delete:
            delete_message(message, path)
        return content
    return None

def request_state(requestable, path, timeout=3, delete_rec=False):
    # actively requests state from listener
    receivable = requestable.replace("req", "rec")
    delete_message(receivable, path=path)
    send_message(requestable, path=path)
    i = 0
    content=None
    while i<timeout:
        content = receive_message(receivable, path=path, delete=delete_rec)
        if content is not None:
            break
        time.sleep(0.3)
        i+=1
    delete_message(requestable, path=path)
    return content

def get_downloads(path):
    targetfolder = os.path.join(OUTPUT_FOLDER, path)
    targetfolder = os.path.abspath(targetfolder)
    if os.path.exists(targetfolder):
        files = os.listdir(targetfolder)
        downloadables = [file for file in files if len(re.findall('^\d+_\d+\.zip$', file))>0] 
        return downloadables
    return []

def get_download_path(path):
    # return full download path
    try:
        targetfolder = os.path.join(OUTPUT_FOLDER, path)
        targetfolder = os.path.abspath(targetfolder)
        return targetfolder
    except Exception as e:
        return None
    
def state_listener_callback(object):
    if not os.path.exists(object.targetfolder):
        return
    files = os.listdir(object.targetfolder)
    if REQ_CANCEL in files:
        object.cancel()
        send_message(message=REC_CANCEL, path=object.targetfolder)
        delete_message(message=REQ_CANCEL, path=object.targetfolder)
    if REQ_PAUSE in files: # cancel && send rec with progress
        object.cancel()
        send_message(message=REC_PAUSE, path=object.targetfolder, data=object.progress)
        delete_message(message=REQ_PAUSE, path=object.targetfolder)
    if REQ_PROGRESS in files:
        send_message(message=REC_PROGRESS, path=object.targetfolder, data=object.progress)
        delete_message(message=REQ_PROGRESS, path=object.targetfolder)
    if REQ_ACTIVE in files:
        send_message(message=REC_ACTIVE, path=object.targetfolder, data=object.progress)
        delete_message(message=REQ_ACTIVE, path=object.targetfolder)
    if object.progress==object.total:
        send_message(REC_COMPLETE, path=object.targetfolder, data=object.progress)

def start_server_generation(generator,start_number,limit,qr_serial_length,csv_serial_length,pre_string,pro_string,progress):
    # starts a threaded generator and assigns it a threaded listener
    global state_listener_callback
    generator.register_callback(state_listener_callback)
    def generate_fn():
        generator.generate(start_number,limit,qr_serial_length,csv_serial_length,pre_string,pro_string,progress)
        if generator.progress==generator.total:
            send_message(REC_COMPLETE, path=generator.targetfolder, data=generator.progress)
        delete_message(REC_ACTIVE, path=generator.targetfolder)
    generate_thread = threading.Thread(target=generate_fn, args=())
    generate_thread.start()
    return True

#gen = QRCodeGenerator("name", 1, qrcode.constants.ERROR_CORRECT_M, 4, 1, 1000, fgcolor=(0,0,0), bgcolor=(255,255,255), folder="test\\test1")
#gen.generate(1, 50, 5, 5, "TS-", "", progress=40)
#start_server_generation(gen, 1, 10000, 5, 5, "TS-", "", progress=1519)