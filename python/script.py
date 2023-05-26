import utils
import sys
import time
import subprocess
import platform

PLATFORM = platform.system()

param_string = sys.argv[1]
#param_string = "{type:generate,string:superadmin/f__full_test,start:1,total:20000,qlength:5,clength:5,pre_string:FT-,pro_string:,batch:1000,error_level:ERROR_CORRECT_M,box_size:4,border:1,fgcolor:#ff0000,bgcolor:#00ff00,progress:0}"

request_types = ["preview", "generate", "cancel", "force_cancel", "active", "progress", "pause", "state", "downloads", "download_link", "delete"]

def clean_response(res):
    return str(res).replace('"', '`').replace("'", "`")
    
param_dict = utils.dict_from_param_string(param_string)
if param_dict is None:
    print("{'status':'failed','message':'failed to parse cmd args `{}`'}".format(param_string))
else:
    response = {"status":"failed", "data":"No response!"}
    request_type = param_dict["type"]
    if request_type not in request_types:
        response = "{'status':'failed', 'message':'unknown command `"+request_type+"`'}"
    elif (request_type=="preview"):
        response = utils.generate_image_b64_preview(
            string=param_dict['string'],
            error_correction=param_dict['error_level'],
            box_size=param_dict['box_size'],
            border=param_dict['border'],
            fgcolor=param_dict['fgcolor'],
            bgcolor=param_dict['bgcolor'],
            version=1
        )
    elif request_type=="generate":
        # communicate : pause<>; cancel<; progress<>
        try:
            active_state = utils.request_state(utils.REQ_ACTIVE, path=param_dict["string"])
            cancel_state = utils.receive_message(utils.REC_CANCEL, path=param_dict['string'], delete=False)
            complete_state = utils.receive_message(utils.REC_COMPLETE, path=param_dict['string'], delete=False)
            startable = True
            if active_state is not None:
                is_active=True
                response = {"status":"failed","message":"Cannot run an already-active project!"}
            elif cancel_state is not None:
                startable=False
                response = {"status":"failed","message":"Cannot start cancelled project!"}
            elif complete_state is not None:
                startable=False
                response = {"status":"failed","message":"Cannot start complete project!"}
            if startable:
                command = ['python', 'python/generate.py', param_string] # os.abspath
                if PLATFORM=="Windows":
                    subprocess.Popen(command)
                else:
                    subprocess.Popen(command, start_new_session=True) # or use fork
                response = {"status":"success", "data":"Project running!"}
        except Exception as e:
            response = {"status":"failed", "message":clean_response("An error occured on our end - {}".format(e))}
    elif request_type=="cancel": # cancel project
        try:
            complete_state = utils.receive_message(utils.REC_COMPLETE, path=param_dict['string'], delete=False)
            if complete_state is not None:
                response = {"status":"failed","message":"Cannot cancel completed project!"}
            else:
                received = utils.request_state(utils.REQ_CANCEL, path=param_dict["string"])
                if received is not None:
                    utils.delete_message(utils.REC_ACTIVE,path=param_dict["string"])
                    utils.delete_message(utils.REC_PAUSE,path=param_dict["string"])
                    response = {"status":"success", "data":"Cancel request sent!"}
        except Exception as e:
            response = {"status":"failed", "message":clean_response("Error:{}".format(e))}
    elif request_type=="progress": # get progress
        try:
            response = {"status":"failed", "data":"No response!"}
            complete_content = utils.receive_message(utils.REC_COMPLETE, path=param_dict["string"], delete=False)
            pause_content = utils.receive_message(utils.REC_PAUSE, path=param_dict["string"], delete=False)
            if complete_content is not None:
                response = {"status":"success", "data":complete_content}
            elif pause_content is not None:
                response = {"status":"success", "data":pause_content}
            else:
                received = utils.request_state(utils.REQ_PROGRESS, path=param_dict["string"])
                response = {"status":"success", "data":0} # e.g canceled
                if received is not None:
                    response = {"status":"success", "data":received}
        except Exception as e:
            response = {"status":"failed", "message":clean_response("Error:{}".format(e))}
    elif request_type=="active": # check if still active
        try:
            received = utils.request_state(utils.REQ_ACTIVE, path=param_dict["string"])
            if received is not None:
                response = {"status":"success", "data":received}
        except Exception as e:
            response = {"status":"failed", "message":clean_response("Error:{}".format(e))}
    elif request_type=="pause": # set pause and return progress
        response = {"status":"failed", "data":"No response!"}
        try:
            active_state = utils.request_state(utils.REQ_ACTIVE, path=param_dict["string"])
            pause_state = utils.receive_message(utils.REC_PAUSE, path=param_dict['string'], delete=False)
            cancel_state = utils.receive_message(utils.REC_CANCEL, path=param_dict['string'], delete=False)
            complete_state = utils.receive_message(utils.REC_COMPLETE, path=param_dict['string'], delete=False)
            if cancel_state is not None:
                response = {"status":"failed","message":"Cannot pause cancelled project!"}
            elif complete_state is not None:
                response = {"status":"failed","message":"Cannot pause complete project!"}
            elif pause_state is not None:
                response = {"status":"failed","message":"Project already paused!"}
            elif active_state is not None:
                received = utils.request_state(utils.REQ_PAUSE, path=param_dict["string"])
                if received:
                    utils.delete_message(utils.REC_ACTIVE, path=param_dict['string'])
                    response = {"status":"success", "data":received}
        except Exception as e:
            response = {"status":"failed", "message":clean_response("Error:{}".format(e))}
    elif request_type=="state":
        try:
            pause_state = utils.receive_message(utils.REC_PAUSE, path=param_dict['string'], delete=False)
            cancel_state = utils.receive_message(utils.REC_CANCEL, path=param_dict['string'], delete=False)
            complete_state = utils.receive_message(utils.REC_COMPLETE, path=param_dict['string'], delete=False)
            state=None
            if pause_state is not None:
                state="PAUSED"
            elif cancel_state is not None:
                state = "CANCELLED"
            elif complete_state is not None:
                state = "COMPLETE"
            if state is None:
                state="PAUSED"
                active_state = utils.request_state(utils.REQ_ACTIVE, path=param_dict["string"])
                if active_state is not None:
                    state = "ACTIVE"
            response = {"status":"success", "data":state}
        except Exception as e:
            response = {"status":"failed", "message":clean_response("Error:{}".format(e))}
    elif request_type=="downloads": # returns list of downloads
        response = {"status":"success", "data":utils.get_downloads(path=param_dict["string"])}
    elif request_type=="download_link": # return full path to download
        dpath = utils.get_download_path(path=param_dict["string"])
        if dpath is None:
            response = {"status":"failed", "message":"An -error occured!"}
        else:
            response = {"status":"success", "data":dpath}
    elif request_type=="delete":
        generator = utils.QRCodeGenerator(str(time.time()), 1, folder=param_dict["string"])
        generator.delete_files()
        response = {"status":"success", "data":"Operation successfull!"}
    elif request_type=="force_cancel":
        utils.delete_message(message=utils.REC_ACTIVE,  path=param_dict["string"])
        utils.delete_message(message=utils.REC_PAUSE,  path=param_dict["string"])
        utils.send_message(message=utils.REC_CANCEL, path=param_dict["string"])
        response = {"status":"success", "data":"Operation successfull!" + param_dict["string"]}

    print(response)