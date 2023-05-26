import utils
import sys
import time

#param_string = "{type:generate,string:superadmin/ffull_test,start:1,total:10000,qlength:5,clength:5,pre_string:FT-,pro_string:,batch:1000,error_level:ERROR_CORRECT_M,box_size:4,border:1,fgcolor:#ff0000,bgcolor:#00ff00,progress:0}"

param_string = None
args = sys.argv
for arg in args:
    if '{' in arg and '}' in arg:
        start_index = arg.index('{')
        end_index = arg.index('}')
        param_string = arg[start_index:end_index+1]
        break
param_dict = utils.dict_from_param_string(param_string)

if param_dict is None:
    print("{'status':'failed','message':'failed to parse cmd args `{0}`'}".format(param_string))
else:
    _error_correction = utils.get_error_correction_level(param_dict['error_level'])
    _fg_color = utils.hex_to_rgb(param_dict['fgcolor'])
    _bg_color = utils.hex_to_rgb(param_dict['bgcolor'])
    _start = int(param_dict["start"])
    _total = int(param_dict["total"])
    _qr_serial_length=int(param_dict["qlength"])
    _csv_serial_length=int(param_dict["clength"])
    _border = int(param_dict['box_size'])
    _folder_batch = int(param_dict['batch'])
    _box_size = int(param_dict['box_size'])

    generator = utils.QRCodeGenerator(
        name=str(time.time()), 
        version=1, 
        error_correction=_error_correction, 
        box_size=_box_size,
        border=_border,
        folder_batch=_folder_batch,
        fgcolor=_fg_color,
        bgcolor=_bg_color,
        folder=param_dict['string']
    )

    utils.start_server_generation(
        generator,
        start_number=_start, 
        limit=_total, 
        qr_serial_length=_qr_serial_length, 
        csv_serial_length=_csv_serial_length, 
        pre_string=param_dict["pre_string"],
        pro_string=param_dict["pro_string"],
        progress=int(param_dict["progress"])
    )

    utils.send_message(utils.REC_ACTIVE, path=param_dict['string'])
    utils.delete_message(utils.REC_PAUSE, path=param_dict['string'])