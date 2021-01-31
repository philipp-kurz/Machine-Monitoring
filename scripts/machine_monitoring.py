#!/usr/bin/python3

import paho.mqtt.client as mqtt
import mysql.connector
import time
import json
 
from credentials import credentials as creds
from telegram_notifications import TelegramBot

from machine_monitoring_enums import MonitoringMode, MachineStatus
    
class Machine: 
    
    def __init__(self, number, db, db_cursor, telegram_bot):
        self.machine_number = number
        self.db = db
        self.db_cursor = db_cursor
        self.telegram_bot = telegram_bot
 
    def get_latest_status(self, before):     
        query = f"SELECT `status` FROM `machine_data` WHERE `machine` = {self.machine_number} \
                 AND message_time <= '{before}' ORDER BY `message_time` desc LIMIT 1"
        self.db_cursor.execute(query)
        result = self.db_cursor.fetchall()
        return result[0][0]

    def update(self, new_status, message_time):
        latest_status = self.get_latest_status(message_time)
        if new_status != latest_status:
            query = f"INSERT INTO machine_data (machine, status, message_time) \
                     VALUES ({self.machine_number}, {new_status}, '{message_time}')"
            self.db_cursor.execute(query)
            self.db.commit()
            # print(f"Updated machine {self.machine_number} with status {new_status.name}.")
            
            if new_status == MachineStatus.STOPPED_ACTIVE:
                self.telegram_bot.notify(self.machine_number, new_status, message_time)
                
    
    @staticmethod
    def get_updates_from_hex_input(hex_input):
        
        # Check if hex input has correct length
        if len(hex_input) != 4:
            print('Input did not have length 4!')
            return None
        
        # Convert hex to binary
        boolean_inputs = None
        try:
            boolean_inputs = bin(int(hex_input, base=16))
        except ValueError:
            print('Input was not in hex format')
            return None

        # Process binary data
        boolean_inputs = str(boolean_inputs[2:]).rjust(16, '0')
        boolean_inputs = boolean_inputs[::-1]
        boolean_inputs = [bit == '1' for bit in boolean_inputs]
                
        # Slice into actual data
        result = []
        instantly = boolean_inputs[0:4]
        delayed = boolean_inputs[4:8]
        malfunction = boolean_inputs[8:12]
        stopped = boolean_inputs[12:16]        
        
        # Construct results
        for machine in range(4):
        
            # Get mode
            mode = MonitoringMode.INACTIVE            
            if instantly[machine] and delayed[machine]:
                print('Invalid inputs: Can\'t be set to instantly and delayed at the same time!')
                return None
                
            elif instantly[machine]:
                mode = MonitoringMode.ACTIVE_INSTANTLY
                
            elif delayed[machine]:
                mode = MonitoringMode.ACTIVE_DELAYED_MANUAL
                
            # Get status
            status = None
            if stopped[machine]: # If machine has stopped
            
                if mode == MonitoringMode.INACTIVE: # Machine stopped, but monitoring inactive
                    status = MachineStatus.STOPPED_INACTIVE
                
                elif mode == MonitoringMode.ACTIVE_INSTANTLY: # Machine stopped and monitoring active
                    status = MachineStatus.STOPPED_ACTIVE
                
                elif mode == MonitoringMode.ACTIVE_DELAYED_MANUAL: 
                    
                    if malfunction[machine]: # Machine stopped, monitoring delayed and timer elapsed
                        status = MachineStatus.STOPPED_ACTIVE
                        
                    else: # Machine stopped, monitoring delayed and timer not elapsed yet
                        status = MachineStatus.STOPPED_ACTIVE_DELAY_TICKING
                   
                elif mode == MonitoringMode.ACTIVE_DELAYED_SOFTWARE: 
                    print('Not yet implemented! Abort.')
                    return None
           
            else: # If machine is running
                if mode != MonitoringMode.INACTIVE: # Machine running and monitoring active
                    status = MachineStatus.RUNNING_ACTIVE
                else: # Machine running and monitoring inactive
                    status = MachineStatus.RUNNING_INACTIVE
                    
            result.append(status)
            
        return result

        
if __name__ == '__main__':
    
    while True:
        try:
            db = mysql.connector.connect(      
              host=creds['mysql']['host'],
              user=creds['mysql']['username'],
              password=creds['mysql']['password'],
              database=creds['mysql']['database'],
            )    
        except:
            print('Error occured while connecting to database.')
            db = None
        if db:
            print(f"Connected successfully to MySQL database at {creds['mysql']['host']}.")
            break
        time.sleep(10)
    db_cursor = db.cursor()
    
    telegram_bot = TelegramBot(creds['telegram']['bot_token'], db, db_cursor)
    
    # Set up machine objects
    machines = []
    for machine in range(1,5):
        machines.append(Machine(machine, db, db_cursor, telegram_bot))
    
    # Implement MQTT callback functions
    def on_mqtt_connect(client, userdata, flags, rc):
        print(f"Connected to MQTT broker at {creds['mqtt']['host']} with result code: {rc}")

    def on_mqtt_message(client, userdata, msg):
        # print(f'Topic: {msg.topic} - Message: {msg.payload}')
        data = json.loads(msg.payload)
        
        # Insert into mqtt_messages
        query = "INSERT INTO mqtt_messages (message, message_time) VALUES (%(message)s, %(message_time)s)"
        values = {'message': msg.payload, 'message_time': data['date']}
        db_cursor.execute(query, values)
        db.commit()
        
        # Parse digital inputs
        status_updates = Machine.get_updates_from_hex_input(data['digital_inputs'])
        
        # Update machine statuses
        for i, machine in enumerate(machines):
            machine.update(status_updates[i], data['date'])
 
    # Connect to MQTT broker
    client = mqtt.Client()
    client.username_pw_set(creds['mqtt']['username'], password=creds['mqtt']['password'])
    client.on_connect = on_mqtt_connect
    client.on_message = on_mqtt_message
    client.connect(creds['mqtt']['host'])
    client.subscribe(creds['mqtt']['topic'])
    
    # Loop
    client.loop_forever()
