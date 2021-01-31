import telepot
import sys
import time 
import datetime
import mysql.connector
import os
from machine_monitoring_enums import MonitoringMode, MachineStatus


def GenerateTimeDiffStr(tdDelta):
    Date = dict(days=tdDelta.days)
    Date['hrs'], rem = divmod(tdDelta.seconds, 3600)
    Date['min'], Date['sec'] = divmod(rem, 60)

    if   Date['min'] is 0:
        sReturn = '{sec} Sek.'
    elif Date['hrs'] is 0:
        sReturn = '{min} Min.'
    elif Date['days'] is 0:
        sReturn = '{hrs} Stunde(n) {min} Min.'
    else:
        sReturn = '{days} Tag(e) {hrs} Stunde(n)'

    return sReturn.format(**Date)   


class TelegramBot:
    
    def __init__(self, bot_token, db, db_cursor):    
        self.bot = telepot.Bot(bot_token)
        self.db = db
        self.db_cursor = db_cursor
        
    def notify(self, machine_number, new_status, message_time):
        # Get machine name
        query = f"SELECT `name` FROM `Machines` WHERE `id` = {machine_number}"
        self.db_cursor.execute(query)
        result = self.db_cursor.fetchall()
        machine_name = result[0][0]
    
        # Get time difference
        query = f"SELECT `message_time` FROM `machine_data` WHERE `machine` = {machine_number} \
                 AND message_time < '{message_time}' ORDER BY `message_time` desc LIMIT 1"
        self.db_cursor.execute(query)
        result = self.db_cursor.fetchall()
        
        previous_time = str(result[0][0])
        db_time_format = '%Y-%m-%d %H:%M:%S'
        previous_time = datetime.datetime.strptime(previous_time, db_time_format).replace(microsecond=0)  
        
        plc_time_format = '%Y-%m-%d-%H:%M:%S'
        message_time = datetime.datetime.strptime(message_time, plc_time_format).replace(microsecond=0)
        
        time_diff = message_time - previous_time
        time_diff = GenerateTimeDiffStr(time_diff)
        
        # Get recipients
        query = "SELECT chatId from TelegramUsers WHERE enable = 1"
        self.db_cursor.execute(query)
        result = self.db_cursor.fetchall()        
        recipients = [recipient[0] for recipient in result]

        for chat_id in recipients:
            time_str = message_time.strftime('%H:%M')
            text = f"_{time_str}_ - *{machine_name}*: "
            
            if new_status == MachineStatus.STOPPED_ACTIVE:
                number_symbol = chr(ord('\u0030') + machine_number) + "\ufe0f\u20e3"
                text += f"Störung \u274c{number_symbol}\n_(Laufzeit: {time_diff})_"
                
            self.bot.sendMessage(chat_id, text, parse_mode='Markdown')
    


 
 



